<?php

declare(strict_types=1);

use Liberu\Ecommerce\ReviewsAndRatings\Actions\DecideModeration;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionRevision;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\AggregatePopulation;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\DisplayedRatingAggregate;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\RecordedRatingTotal;

beforeEach(function () {
    bindScreener();
});

function rate(int $score, string $author, int $scale = 5, bool $approved = true): string
{
    $receipt = submit(submission([
        'authorReference' => $author,
        'score' => $score,
        'scale' => $scale,
        'body' => null,
    ]));

    if ($approved) {
        approve($receipt->reference);
    }

    return $receipt->reference;
}

it('publishes a numerator and a denominator, never a rounded float', function () {
    rate(4, 'shopper:a');
    rate(5, 'shopper:b');

    $aggregate = app(DisplayedRatingAggregate::class)(TENANT, PRODUCT);

    expect($aggregate->sum)->toBe(9)
        ->and($aggregate->count)->toBe(2)
        ->and($aggregate->scale)->toBe(5)
        ->and($aggregate->toArray())->toBe(['sum' => 9, 'count' => 2, 'scale' => 5, 'population' => 'displayed']);
});

it('names the population it summarises', function () {
    expect(app(DisplayedRatingAggregate::class)(TENANT, PRODUCT)->population)->toBe(AggregatePopulation::Displayed)
        ->and(app(RecordedRatingTotal::class)(TENANT, PRODUCT)->population)->toBe(AggregatePopulation::Recorded);
});

/*
 * The host's moderation bypass: post an abusive review with a one-star score,
 * the sentence waits for a moderator, and the star lands in the public average
 * immediately, because the average is over every rating regardless of
 * moderation. A rating here is a moderatable expression like any other.
 */
it('leaves an undecided rating out of the published figure', function () {
    rate(5, 'shopper:a');
    rate(1, 'shopper:troll', approved: false);

    expect(app(DisplayedRatingAggregate::class)(TENANT, PRODUCT)->sum)->toBe(5)
        ->and(app(DisplayedRatingAggregate::class)(TENANT, PRODUCT)->count)->toBe(1);
});

it('leaves a withheld rating out of the published figure', function () {
    rate(5, 'shopper:a');
    $withheld = rate(1, 'shopper:troll', approved: false);
    decide($withheld, ModerationOutcome::Withheld, ModerationReason::HarassmentOrHate);

    expect(app(DisplayedRatingAggregate::class)(TENANT, PRODUCT)->count)->toBe(1);
});

it('counts everything recorded in the staff figure, including what was withheld', function () {
    rate(5, 'shopper:a');
    $withheld = rate(1, 'shopper:troll', approved: false);
    decide($withheld, ModerationOutcome::Withheld, ModerationReason::HarassmentOrHate);

    $recorded = app(RecordedRatingTotal::class)(TENANT, PRODUCT);

    expect($recorded->sum)->toBe(6)->and($recorded->count)->toBe(2);
});

it('counts a superseded rating in neither figure', function () {
    $first = submit(submission(['score' => 1, 'scale' => 5, 'body' => null]));
    approve($first->reference);
    $second = revise($first->reference, new ExpressionRevision(score: 5, scale: 5));
    approve($second->reference);

    expect(app(DisplayedRatingAggregate::class)(TENANT, PRODUCT)->sum)->toBe(5)
        ->and(app(RecordedRatingTotal::class)(TENANT, PRODUCT)->count)->toBe(1);
});

it('never mixes two scales into one meaningless number', function () {
    rate(4, 'shopper:a', 5);
    rate(9, 'shopper:b', 10);

    expect(app(DisplayedRatingAggregate::class)(TENANT, PRODUCT, 5)->sum)->toBe(4)
        ->and(app(DisplayedRatingAggregate::class)(TENANT, PRODUCT, 10)->sum)->toBe(9)
        ->and(app(DisplayedRatingAggregate::class)(TENANT, PRODUCT, 5)->count)->toBe(1);
});

it('ignores an expression that carried words but no number', function () {
    rate(4, 'shopper:a');
    $wordsOnly = submit(submission(['authorReference' => 'shopper:b', 'score' => null, 'scale' => null, 'body' => 'no number from me']));
    approve($wordsOnly->reference);

    expect(app(DisplayedRatingAggregate::class)(TENANT, PRODUCT)->count)->toBe(1);
});

it('keeps one tenant figures out of another', function () {
    rate(5, 'shopper:a');
    $theirs = submit(submission(['tenantId' => 'tenant-beta', 'score' => 1, 'scale' => 5, 'body' => null]));
    app(DecideModeration::class)(
        'tenant-beta',
        $theirs->reference,
        ModerationOutcome::Approved,
        ModerationReason::Compliant,
        'staff:other',
    );

    expect(app(DisplayedRatingAggregate::class)(TENANT, PRODUCT)->sum)->toBe(5);
});

it('reports an empty population honestly rather than as a zero star product', function () {
    $aggregate = app(DisplayedRatingAggregate::class)(TENANT, 'catalogue:nobody-has-reviewed-this');

    expect($aggregate->sum)->toBe(0)->and($aggregate->count)->toBe(0);
});
