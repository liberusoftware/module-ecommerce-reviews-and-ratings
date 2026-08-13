<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionRevision;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionSource;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\IncentiveDisclosure;
use Liberu\Ecommerce\ReviewsAndRatings\Events\ExpressionRevised;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionAlreadySuperseded;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionIsImmutable;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionNotFound;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;

beforeEach(function () {
    bindScreener();
});

it('writes an edit as a new row and leaves the old one saying what it said', function () {
    $first = submit(submission(['score' => 1, 'body' => 'Broken on arrival.']));

    revise($first->reference, new ExpressionRevision(score: 5, scale: 5, body: 'Support replaced it, delighted.'));

    $rows = Expression::query()->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->body)->toBe('Broken on arrival.')
        ->and($rows[0]->score)->toBe(1)
        ->and($rows[0]->superseded_at)->not->toBeNull()
        ->and($rows[1]->score)->toBe(5)
        ->and($rows[1]->supersedes_id)->toBe($rows[0]->id);
});

it('starts the revision pending however the original was decided', function () {
    $first = submit(submission(['body' => 'first']));
    approve($first->reference);

    $second = revise($first->reference, new ExpressionRevision(score: 3, scale: 5, body: 'second'));

    expect($second->displayState)->toBe(DisplayState::Pending);
});

it('refuses to revise an expression that has already been revised', function () {
    $first = submit(submission(['body' => 'first']));
    revise($first->reference, new ExpressionRevision(score: 3, scale: 5, body: 'second'));

    expect(fn () => revise($first->reference, new ExpressionRevision(score: 2, scale: 5, body: 'third')))
        ->toThrow(ExpressionAlreadySuperseded::class);
});

it('carries the author, the product and the source across a revision', function () {
    $first = submit(submission([
        'body' => 'first',
        'source' => ExpressionSource::Syndicated,
        'sourceReference' => 'other:1',
    ]));

    revise($first->reference, new ExpressionRevision(score: 2, scale: 5, body: 'second'));

    $latest = Expression::query()->orderByDesc('id')->first();

    expect($latest->author_reference)->toBe(AUTHOR)
        ->and($latest->product_reference)->toBe(PRODUCT)
        ->and($latest->source)->toBe(ExpressionSource::Syndicated)
        ->and($latest->source_reference)->toBe('other:1');
});

it('keeps the previous incentive disclosure when a revision does not restate it', function () {
    $first = submit(submission(['body' => 'first', 'incentive' => IncentiveDisclosure::FreeProduct]));

    revise($first->reference, new ExpressionRevision(score: 2, scale: 5, body: 'second'));

    expect(Expression::query()->orderByDesc('id')->first()->incentive)->toBe(IncentiveDisclosure::FreeProduct);
});

it('says whether the thing it superseded was on display', function () {
    Event::fake([ExpressionRevised::class]);

    $first = submit(submission(['body' => 'first']));
    approve($first->reference);
    revise($first->reference, new ExpressionRevision(score: 2, scale: 5, body: 'second'));

    Event::assertDispatched(ExpressionRevised::class, fn (ExpressionRevised $event): bool => $event->supersededWasDisplayed === true);
});

it('refuses to revise an expression nobody has heard of', function () {
    expect(fn () => revise('00000000000000000000000000000000', new ExpressionRevision(score: 1, scale: 5)))
        ->toThrow(ExpressionNotFound::class);
});

/*
 * The guard below is the trap this module is built around. `getOriginal()`
 * returns the *cast* value for an enum-cast attribute, so an append-only check
 * comparing it to a raw column value compares an enum object against a string
 * and is silently always unequal — which means either it fires on every save or,
 * written the other way round, it never fires at all and the suite still passes.
 */
it('refuses to rewrite what was said', function (string $column, mixed $value) {
    submit(submission(['body' => 'as written']));

    $expression = Expression::query()->sole();
    $expression->{$column} = $value;

    expect(fn () => $expression->save())->toThrow(ExpressionIsImmutable::class);
})->with([
    'the score' => ['score', 2],
    'the scale' => ['scale', 10],
    'the product it is about' => ['product_reference', 'catalogue:something-else'],
    'the moment it happened' => ['occurred_at', '2020-01-01 00:00:00'],
    'the tenant that owns it' => ['tenant_id', 'tenant-beta'],
    'its public reference' => ['reference', str_repeat('f', 32)],
]);

it('refuses to rewrite an enum-cast column, which is the one a naive guard misses', function (string $column, mixed $value) {
    submit(submission(['body' => 'as written']));

    $expression = Expression::query()->sole();
    $expression->{$column} = $value;

    expect(fn () => $expression->save())->toThrow(ExpressionIsImmutable::class);
})->with([
    'where it came from' => ['source', ExpressionSource::Imported],
    'what the author was given for it' => ['incentive', IncentiveDisclosure::Payment],
]);

it('refuses to replace content with different content under the guise of redaction', function () {
    submit(submission(['body' => 'as written']));

    $expression = Expression::query()->sole();
    $expression->body = 'quietly improved';

    expect(fn () => $expression->save())->toThrow(ExpressionIsImmutable::class);
});

it('allows the three things a recorded expression may still do', function () {
    submit(submission(['body' => 'as written']));

    $expression = Expression::query()->sole();
    $expression->superseded_at = now();
    $expression->save();

    expect(Expression::query()->sole()->superseded_at)->not->toBeNull();
});

it('releases the live slot when an expression is superseded so the successor can take it', function () {
    $first = submit(submission(['body' => 'first']));
    revise($first->reference, new ExpressionRevision(score: 3, scale: 5, body: 'second'));

    $rows = Expression::query()->orderBy('id')->get();

    expect($rows[0]->live_key)->toBeNull()
        ->and($rows[1]->live_key)->not->toBeNull();
});
