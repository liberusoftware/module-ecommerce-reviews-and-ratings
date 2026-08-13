<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\ReviewsAndRatings\Data\FacetSubmission;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionKind;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionSource;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\FacetKind;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\IncentiveDisclosure;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VerificationState;
use Liberu\Ecommerce\ReviewsAndRatings\Events\ExpressionRecorded;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\DuplicateExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\InvalidExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Liberu\Ecommerce\ReviewsAndRatings\Support\SubmissionRules;

beforeEach(function () {
    bindScreener();
});

it('records a star and a sentence as one expression, not two rows', function () {
    submit(submission(['body' => 'Solid, and it fits.']));

    $rows = Expression::query()->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->score)->toBe(4)
        ->and($rows->first()->scale)->toBe(5)
        ->and($rows->first()->body)->toBe('Solid, and it fits.');
});

it('accepts a star with no words', function () {
    $receipt = submit(submission(['body' => null]));

    expect($receipt->reference)->not->toBe('');
});

it('accepts words with no star', function () {
    $receipt = submit(submission(['score' => null, 'scale' => null, 'body' => 'No number from me.']));

    expect($receipt->reference)->not->toBe('');
});

it('records nothing as displayed by arriving', function () {
    $receipt = submit(submission(['body' => 'Hello.']));

    expect($receipt->displayState)->toBe(DisplayState::Pending)
        ->and(Expression::query()->sole()->isDisplayed())->toBeFalse();
});

it('refuses a score without the scale it was given on', function () {
    expect(fn () => submit(submission(['scale' => null])))
        ->toThrow(InvalidExpression::class);
});

it('refuses a scale without a score', function () {
    expect(fn () => submit(submission(['score' => null, 'body' => 'words'])))
        ->toThrow(InvalidExpression::class);
});

it('refuses an expression that says nothing at all', function () {
    expect(fn () => submit(submission(['score' => null, 'scale' => null, 'body' => null])))
        ->toThrow(InvalidExpression::class);
});

it('refuses a score that is not on its own scale', function () {
    expect(fn () => submit(submission(['score' => 7, 'scale' => 5])))
        ->toThrow(InvalidExpression::class);
});

it('refuses a scale nobody could interpret', function () {
    expect(fn () => submit(submission(['score' => 1, 'scale' => 1])))
        ->toThrow(InvalidExpression::class);
});

it('refuses a body longer than the one limit every surface reads', function () {
    expect(fn () => submit(submission(['body' => str_repeat('a', SubmissionRules::MAX_BODY_LENGTH + 1)])))
        ->toThrow(InvalidExpression::class);
});

it('refuses a body that is only whitespace rather than storing it', function () {
    expect(fn () => submit(submission(['body' => "   \n  "])))
        ->toThrow(InvalidExpression::class);
});

it('refuses an expression with no display name for its author', function () {
    expect(fn () => submit(submission(['authorDisplayName' => ' '])))
        ->toThrow(InvalidExpression::class);
});

it('stores the display name it was given and never looks one up', function () {
    submit(submission(['authorDisplayName' => '  Sarah T.  ']));

    expect(Expression::query()->sole()->author_display_name)->toBe('Sarah T.');
});

it('stores breakdown scores as rows, each carrying its own scale', function () {
    submit(submission(['facets' => [
        new FacetSubmission(FacetKind::Quality, 5, 5),
        new FacetSubmission(FacetKind::Value, 3, 5),
    ]]));

    $facets = Expression::query()->sole()->facets;

    expect($facets)->toHaveCount(2)
        ->and($facets->pluck('scale')->all())->toBe([5, 5]);
});

it('refuses the same facet twice on one expression', function () {
    expect(fn () => submit(submission(['facets' => [
        new FacetSubmission(FacetKind::Quality, 5, 5),
        new FacetSubmission(FacetKind::Quality, 4, 5),
    ]])))->toThrow(InvalidExpression::class);
});

it('refuses a facet score that is not on its own scale', function () {
    expect(fn () => submit(submission(['facets' => [new FacetSubmission(FacetKind::Price, 9, 5)]])))
        ->toThrow(InvalidExpression::class);
});

it('lets a facet stand on its own without an overall score', function () {
    $receipt = submit(submission([
        'score' => null,
        'scale' => null,
        'facets' => [new FacetSubmission(FacetKind::Delivery, 2, 5)],
    ]));

    expect($receipt->reference)->not->toBe('');
});

it('refuses a second live expression from the same author on the same product', function () {
    submit(submission());

    expect(fn () => submit(submission()))->toThrow(DuplicateExpression::class);
});

it('lets the same author speak about a different product', function () {
    submit(submission());
    submit(submission(['productReference' => 'catalogue:sku-other']));

    expect(Expression::query()->count())->toBe(2);
});

it('keeps tenants apart on the duplicate rule', function () {
    submit(submission());
    submit(submission(['tenantId' => 'tenant-beta']));

    expect(Expression::query()->count())->toBe(2);
});

it('records the moment the thing happened separately from the moment the row appeared', function () {
    $happened = CarbonImmutable::parse('2025-03-04 12:00:00');

    submit(submission(['occurredAt' => $happened]));

    $expression = Expression::query()->sole();

    expect($expression->occurred_at->toDateTimeString())->toBe('2025-03-04 12:00:00')
        ->and($expression->created_at->greaterThan($happened))->toBeTrue();
});

it('defaults an unstated source, incentive and verification without reading the column back', function () {
    submit(submission());

    $expression = Expression::query()->sole();

    expect($expression->source)->toBe(ExpressionSource::FirstParty)
        ->and($expression->incentive)->toBe(IncentiveDisclosure::None)
        ->and($expression->verification)->toBe(VerificationState::Unknown)
        ->and($expression->kind)->toBe(ExpressionKind::ShopperReview);
});

it('records a disclosed incentive as a closed enum', function () {
    submit(submission(['incentive' => IncentiveDisclosure::FreeProduct]));

    expect(Expression::query()->sole()->incentive->isDisclosed())->toBeTrue();
});

it('demands a source reference from history brought in from another platform', function () {
    expect(fn () => submit(submission(['source' => ExpressionSource::Imported])))
        ->toThrow(InvalidExpression::class);
});

it('records imported history with the reference it had there', function () {
    submit(submission(['source' => ExpressionSource::Syndicated, 'sourceReference' => 'trustpilot:99']));

    expect(Expression::query()->sole()->source_reference)->toBe('trustpilot:99');
});

it('announces the recording without announcing the author', function () {
    Event::fake([ExpressionRecorded::class]);

    submit(submission());

    Event::assertDispatched(ExpressionRecorded::class, function (ExpressionRecorded $event): bool {
        return $event->productReference === PRODUCT
            && ! str_contains(json_encode(get_object_vars($event), JSON_THROW_ON_ERROR), AUTHOR);
    });
});

it('gives every expression an opaque reference that is not its row number', function () {
    submit(submission());

    $expression = Expression::query()->sole();

    expect($expression->reference)->toHaveLength(32)
        ->and($expression->reference)->not->toBe((string) $expression->id);
});
