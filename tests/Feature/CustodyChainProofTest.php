<?php

declare(strict_types=1);

use Liberu\Ecommerce\ReviewsAndRatings\Actions\CastVote;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\EraseAuthor;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionRevision;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionKind;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VoteDirection;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Liberu\Ecommerce\ReviewsAndRatings\Models\ModerationDecision;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\DisplayedRatingAggregate;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\PublicReviewListing;

/*
 * The custody chain.
 *
 * One fixture: an author writes, edits, is approved, has the edit withheld,
 * collects votes, gets a merchant reply, and is finally erased. Three
 * independent proofs run over it, because each of the three is the thing a
 * plausible implementation gets wrong on its own.
 */
beforeEach(function () {
    bindScreener();

    // The author the story is about: approved at two stars, then edited to five
    // and held. Nothing they wrote is on display by the end of it.
    $first = submit(submission(['authorReference' => 'shopper:ada', 'score' => 2, 'body' => 'Arrived scratched.']));
    approve($first->reference, 'staff:ana');
    $edit = revise($first->reference, new ExpressionRevision(score: 5, scale: 5, body: 'They replaced it, no complaints.'));
    decide($edit->reference, ModerationOutcome::Withheld, ModerationReason::DuplicateSubmission, 'staff:ben');

    // Three other voices, so the published figure is worth reproducing.
    $this->displayed = [];

    foreach ([['bob', 5], ['cleo', 4], ['dan', 3]] as [$name, $score]) {
        $receipt = submit(submission(['authorReference' => "shopper:{$name}", 'score' => $score, 'body' => "words from {$name}"]));
        approve($receipt->reference, 'staff:cara');
        $this->displayed[$name] = $receipt->reference;
    }

    // And one that a moderator took back down.
    $retracted = submit(submission(['authorReference' => 'shopper:eve', 'score' => 1, 'body' => 'buy pills at']));
    approve($retracted->reference, 'staff:cara');
    decide($retracted->reference, ModerationOutcome::Rejected, ModerationReason::Spam, 'staff:ben');

    app(CastVote::class)(TENANT, $this->displayed['bob'], 'shopper:cleo', VoteDirection::Helpful);
    app(CastVote::class)(TENANT, $this->displayed['bob'], 'shopper:dan', VoteDirection::Unhelpful);

    $reply = reply($this->displayed['cleo'], 'Thanks — we have passed this to the maker.');
    approve($reply->reference, 'staff:ana');
});

it('reproduces the published aggregate from the raw rows alone', function () {
    $published = app(DisplayedRatingAggregate::class)(TENANT, PRODUCT, 5);

    // Recomputed without touching the query under test, without a cached
    // counter, and without the derived display state helper: latest decision by
    // occurrence, then by insertion order, read straight off the rows.
    $sum = 0;
    $count = 0;

    foreach (Expression::query()->get() as $expression) {
        if ($expression->kind !== ExpressionKind::ShopperReview) {
            continue;
        }

        if ($expression->score === null || $expression->scale !== 5 || $expression->superseded_at !== null) {
            continue;
        }

        $latest = ModerationDecision::query()
            ->where('expression_id', $expression->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();

        if ($latest?->outcome !== ModerationOutcome::Approved) {
            continue;
        }

        $sum += $expression->score;
        $count++;
    }

    expect($sum)->toBe(12)
        ->and($count)->toBe(3)
        ->and($published->sum)->toBe($sum)
        ->and($published->count)->toBe($count);
});

it('traces everything displayed to a decision that names an actor', function () {
    $listing = app(PublicReviewListing::class)(TENANT, PRODUCT);

    expect($listing)->toHaveCount(3);

    foreach ($listing as $review) {
        $expression = Expression::query()->where('reference', $review->reference)->sole();

        $decision = ModerationDecision::query()
            ->where('expression_id', $expression->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();

        expect($decision)->not->toBeNull()
            ->and($decision->outcome)->toBe(ModerationOutcome::Approved)
            ->and(trim($decision->actor_reference))->not->toBe('');

        if ($review->reply !== null) {
            $replyRow = Expression::query()->where('reference', $review->reply->reference)->sole();

            expect(ModerationDecision::query()->where('expression_id', $replyRow->id)->where('outcome', ModerationOutcome::Approved->value)->exists())
                ->toBeTrue();
        }
    }
});

it('makes an erased author unreachable from the public projection without moving the figure', function () {
    $before = app(DisplayedRatingAggregate::class)(TENANT, PRODUCT, 5);

    // Two erasures: one author whose live expression was withheld anyway, and
    // one whose five stars are on the page right now. A naive implementation
    // gets these wrong in opposite directions — deleting the row moves the
    // published figure, and merely hiding the row leaves the person reachable.
    app(EraseAuthor::class)(TENANT, 'shopper:ada');
    app(EraseAuthor::class)(TENANT, 'shopper:bob');

    $after = app(DisplayedRatingAggregate::class)(TENANT, PRODUCT, 5);
    $listing = app(PublicReviewListing::class)(TENANT, PRODUCT);
    $serialised = json_encode(array_map(fn ($review) => $review->toArray(), $listing), JSON_THROW_ON_ERROR);

    expect($after->toArray())->toBe($before->toArray())
        ->and($after->count)->toBe(3)
        ->and($listing)->toHaveCount(2)
        ->and($serialised)->not->toContain('shopper:ada')
        ->and($serialised)->not->toContain('shopper:bob')
        ->and($serialised)->not->toContain('words from bob')
        ->and($serialised)->not->toContain('Arrived scratched.');

    // The decisions that put those rows where they are all survive the erasure.
    expect(ModerationDecision::query()->count())->toBe(8);
});
