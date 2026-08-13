<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\CastVote;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\EraseAuthor;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\FlagExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionRevision;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\FlagReason;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VoteDirection;
use Liberu\Ecommerce\ReviewsAndRatings\Events\AuthorErased;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionRedacted;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Flag;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Vote;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\AuthorExport;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\DisplayedRatingAggregate;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\StaffExpression;

beforeEach(function () {
    bindScreener();
    $this->receipt = submit(submission(['score' => 5, 'body' => 'Loved it.']));
    approve($this->receipt->reference);
});

it('removes the content and keeps the shape', function () {
    app(EraseAuthor::class)(TENANT, AUTHOR);

    $row = Expression::query()->sole();

    expect($row->body)->toBeNull()
        ->and($row->author_display_name)->toBeNull()
        ->and($row->author_reference)->toBeNull()
        ->and($row->score)->toBe(5)
        ->and($row->scale)->toBe(5)
        ->and($row->occurred_at)->not->toBeNull()
        ->and($row->redacted_at)->not->toBeNull();
});

it('leaves the published figure exactly where it was', function () {
    $before = app(DisplayedRatingAggregate::class)(TENANT, PRODUCT);

    app(EraseAuthor::class)(TENANT, AUTHOR);

    expect(app(DisplayedRatingAggregate::class)(TENANT, PRODUCT)->toArray())->toBe($before->toArray());
});

it('keeps the moderation history intact, so no decision names a row that is gone', function () {
    app(EraseAuthor::class)(TENANT, AUTHOR);

    $review = app(StaffExpression::class)(TENANT, $this->receipt->reference);

    expect($review->moderationHistory)->toHaveCount(1)
        ->and($review->moderationHistory[0]->actorReference)->toBe('staff:mo')
        ->and($review->displayState)->toBe(DisplayState::Approved)
        ->and($review->isRedacted)->toBeTrue();
});

it('keeps the vote rows and loses the voter', function () {
    app(CastVote::class)(TENANT, $this->receipt->reference, 'shopper:zed', VoteDirection::Helpful);

    app(EraseAuthor::class)(TENANT, 'shopper:zed');

    expect(Vote::query()->count())->toBe(1)
        ->and(Vote::query()->sole()->voter_reference)->toBeNull();
});

it('keeps the report rows and loses the reporter', function () {
    app(FlagExpression::class)(TENANT, $this->receipt->reference, 'shopper:zed', FlagReason::Spam);

    app(EraseAuthor::class)(TENANT, 'shopper:zed');

    expect(Flag::query()->count())->toBe(1)
        ->and(Flag::query()->sole()->reporter_reference)->toBeNull();
});

it('counts what it touched', function () {
    app(CastVote::class)(TENANT, $this->receipt->reference, 'shopper:zed', VoteDirection::Helpful);
    app(FlagExpression::class)(TENANT, $this->receipt->reference, 'shopper:zed', FlagReason::Spam);

    $report = app(EraseAuthor::class)(TENANT, 'shopper:zed');

    expect($report->expressionsRedacted)->toBe(0)
        ->and($report->votesRedacted)->toBe(1)
        ->and($report->flagsRedacted)->toBe(1);
});

it('erases every revision in a chain, not only the live one', function () {
    revise($this->receipt->reference, new ExpressionRevision(score: 1, scale: 5, body: 'changed my mind'));

    $report = app(EraseAuthor::class)(TENANT, AUTHOR);

    expect($report->expressionsRedacted)->toBe(2)
        ->and(Expression::query()->whereNotNull('body')->count())->toBe(0);
});

it('leaves other authors alone', function () {
    submit(submission(['authorReference' => 'shopper:other', 'body' => 'still here']));

    app(EraseAuthor::class)(TENANT, AUTHOR);

    expect(Expression::query()->where('author_reference', 'shopper:other')->sole()->body)->toBe('still here');
});

it('leaves the same author in another tenant alone', function () {
    submit(submission(['tenantId' => 'tenant-beta', 'body' => 'still here']));

    app(EraseAuthor::class)(TENANT, AUTHOR);

    expect(Expression::query()->where('tenant_id', 'tenant-beta')->sole()->body)->toBe('still here');
});

it('returns nothing about an author it has erased', function () {
    expect(app(AuthorExport::class)(TENANT, AUTHOR))->toHaveCount(1);

    app(EraseAuthor::class)(TENANT, AUTHOR);

    expect(app(AuthorExport::class)(TENANT, AUTHOR))->toBe([]);
});

it('refuses to write anything further against a redacted expression', function () {
    app(EraseAuthor::class)(TENANT, AUTHOR);

    expect(fn () => app(CastVote::class)(TENANT, $this->receipt->reference, 'shopper:zed', VoteDirection::Helpful))
        ->toThrow(ExpressionRedacted::class);
    expect(fn () => app(FlagExpression::class)(TENANT, $this->receipt->reference, 'shopper:zed', FlagReason::Spam))
        ->toThrow(ExpressionRedacted::class);
    expect(fn () => revise($this->receipt->reference, new ExpressionRevision(score: 1, scale: 5)))
        ->toThrow(ExpressionRedacted::class);
    expect(fn () => reply($this->receipt->reference))->toThrow(ExpressionRedacted::class);
});

it('announces the erasure in counts and not in content', function () {
    Event::fake([AuthorErased::class]);

    app(EraseAuthor::class)(TENANT, AUTHOR);

    Event::assertDispatched(AuthorErased::class, function (AuthorErased $event): bool {
        return $event->report->expressionsRedacted === 1
            && ! str_contains(json_encode($event->report, JSON_THROW_ON_ERROR), 'Loved it.');
    });
});

it('exports what no page shows the author, including why it was held', function () {
    $held = submit(submission(['authorReference' => 'shopper:held', 'productReference' => 'catalogue:other', 'body' => 'held back']));
    decide($held->reference, ModerationOutcome::Withheld, ModerationReason::OffTopic, 'staff:ana');

    $export = app(AuthorExport::class)(TENANT, 'shopper:held');

    expect($export)->toHaveCount(1)
        ->and($export[0]->displayState)->toBe(DisplayState::Withheld)
        ->and($export[0]->moderationHistory[0]->reason)->toBe(ModerationReason::OffTopic)
        ->and($export[0]->body)->toBe('held back');
});
