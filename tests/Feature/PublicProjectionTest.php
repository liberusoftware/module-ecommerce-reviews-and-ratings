<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\CastVote;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\DecideModeration;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\EraseAuthor;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionRevision;
use Liberu\Ecommerce\ReviewsAndRatings\Data\FacetSubmission;
use Liberu\Ecommerce\ReviewsAndRatings\Data\PublicReview;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\FacetKind;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VoteDirection;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\PublicReviewListing;
use ReflectionClass;

beforeEach(function () {
    bindScreener();
});

/*
 * The host once served every reviewer's postal address from an unauthenticated
 * route, and fixed it with a hand-written column whitelist inside the
 * controller. That fix works and has to be re-remembered on every edit. The
 * defence here is structural instead: the public read model has no field that
 * could carry an identity, so there is no code path to forget.
 */
it('has no field on the public read model that could carry an identity', function () {
    $fields = array_map(
        fn (ReflectionProperty $property): string => strtolower($property->getName()),
        (new ReflectionClass(PublicReview::class))->getProperties(),
    );

    foreach (['authorreference', 'tenantid', 'authorid', 'customer', 'email', 'address', 'phone'] as $forbidden) {
        expect($fields)->not->toContain($forbidden);
    }
});

it('serialises nothing that could be joined back to a person', function () {
    $receipt = submit(submission(['body' => 'a sentence']));
    approve($receipt->reference);

    $serialised = json_encode(app(PublicReviewListing::class)(TENANT, PRODUCT)[0]->toArray(), JSON_THROW_ON_ERROR);

    expect($serialised)->not->toContain(AUTHOR)
        ->and($serialised)->not->toContain(TENANT)
        ->and($serialised)->not->toContain('staff:mo');
});

it('shows the display name the author chose, without resolving anything', function () {
    $receipt = submit(submission(['body' => 'a sentence', 'authorDisplayName' => 'Sarah T.']));
    approve($receipt->reference);

    expect(app(PublicReviewListing::class)(TENANT, PRODUCT)[0]->authorDisplayName)->toBe('Sarah T.');
});

it('shows nothing that no one decided to display', function () {
    submit(submission(['body' => 'never decided']));

    expect(app(PublicReviewListing::class)(TENANT, PRODUCT))->toBe([]);
});

it('stops showing an expression whose approval was retracted', function () {
    $receipt = submit(submission(['body' => 'a sentence']));
    approve($receipt->reference);

    expect(app(PublicReviewListing::class)(TENANT, PRODUCT))->toHaveCount(1);

    decide($receipt->reference, ModerationOutcome::Withheld, ModerationReason::OffTopic);

    expect(app(PublicReviewListing::class)(TENANT, PRODUCT))->toBe([]);
});

it('shows the latest expression in a chain and not the one it replaced', function () {
    $first = submit(submission(['body' => 'first', 'score' => 1]));
    approve($first->reference);
    $second = revise($first->reference, new ExpressionRevision(score: 5, scale: 5, body: 'second'));
    approve($second->reference);

    $listing = app(PublicReviewListing::class)(TENANT, PRODUCT);

    expect($listing)->toHaveCount(1)
        ->and($listing[0]->body)->toBe('second')
        ->and($listing[0]->edited)->toBeTrue();
});

it('leaves a redacted expression out of the listing', function () {
    $receipt = submit(submission(['body' => 'a sentence']));
    approve($receipt->reference);
    app(EraseAuthor::class)(TENANT, AUTHOR);

    expect(app(PublicReviewListing::class)(TENANT, PRODUCT))->toBe([]);
});

it('keeps one tenant out of another tenant listing', function () {
    $ours = submit(submission(['body' => 'ours']));
    approve($ours->reference);
    $theirs = submit(submission(['tenantId' => 'tenant-beta', 'body' => 'theirs']));
    app(DecideModeration::class)(
        'tenant-beta',
        $theirs->reference,
        ModerationOutcome::Approved,
        ModerationReason::Compliant,
        'staff:other',
    );

    $listing = app(PublicReviewListing::class)(TENANT, PRODUCT);

    expect($listing)->toHaveCount(1)->and($listing[0]->body)->toBe('ours');
});

it('carries the votes, the facets and the scale a consumer needs to render', function () {
    $receipt = submit(submission([
        'body' => 'a sentence',
        'facets' => [new FacetSubmission(FacetKind::Quality, 5, 5)],
    ]));
    approve($receipt->reference);
    app(CastVote::class)(
        TENANT,
        $receipt->reference,
        'shopper:zed',
        VoteDirection::Helpful,
    );

    $review = app(PublicReviewListing::class)(TENANT, PRODUCT)[0];

    expect($review->votes->helpful)->toBe(1)
        ->and($review->facets)->toHaveCount(1)
        ->and($review->scale)->toBe(5)
        ->and($review->toArray()['facets'][0])->toBe(['kind' => 'quality', 'score' => 5, 'scale' => 5]);
});

it('pages without repeating itself', function () {
    foreach (range(1, 3) as $index) {
        $receipt = submit(submission([
            'productReference' => PRODUCT,
            'authorReference' => "shopper:{$index}",
            'body' => "review {$index}",
            'occurredAt' => CarbonImmutable::parse('2026-01-0'.$index.' 09:00:00'),
        ]));
        approve($receipt->reference);
    }

    $first = app(PublicReviewListing::class)(TENANT, PRODUCT, 2, 0);
    $second = app(PublicReviewListing::class)(TENANT, PRODUCT, 2, 2);

    expect($first)->toHaveCount(2)
        ->and($second)->toHaveCount(1)
        ->and($first[0]->body)->toBe('review 3')
        ->and($second[0]->body)->toBe('review 1');
});
