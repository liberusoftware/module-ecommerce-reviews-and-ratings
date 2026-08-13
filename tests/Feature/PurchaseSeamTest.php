<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\ReviewsAndRatings\Contracts\ConfirmsPurchase;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionRevision;
use Liberu\Ecommerce\ReviewsAndRatings\Data\PurchaseConfirmation;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionSource;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VerificationState;
use Liberu\Ecommerce\ReviewsAndRatings\Events\PurchaseVerificationDegraded;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\AuthorExport;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\PublicReviewListing;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\StaffExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Tests\Support\StubVerifier;

beforeEach(function () {
    bindScreener();
});

it('treats an unbound verifier as a deployment, not a fault', function () {
    expect(app()->bound(ConfirmsPurchase::class))->toBeFalse();

    $receipt = submit(submission(['body' => 'a sentence']));

    expect($receipt->verification)->toBe(VerificationState::Unknown);
});

it('badges an author the verifier vouched for', function () {
    bindVerifier(new StubVerifier(new PurchaseConfirmation(true, CarbonImmutable::parse('2025-12-01 10:00:00'))));

    $receipt = submit(submission(['body' => 'a sentence']));

    expect($receipt->verification)->toBe(VerificationState::Verified)
        ->and(Expression::query()->sole()->verified_at?->toDateTimeString())->toBe('2025-12-01 10:00:00');
});

it('says unverified only when a verifier actually said no', function () {
    bindVerifier(new StubVerifier(new PurchaseConfirmation(false)));

    expect(submit(submission(['body' => 'a sentence']))->verification)->toBe(VerificationState::Unverified);
});

it('says unknown when the verifier declines to answer', function () {
    bindVerifier(new StubVerifier(null));

    expect(submit(submission(['body' => 'a sentence']))->verification)->toBe(VerificationState::Unknown);
});

it('says unknown and keeps the review when the verifier is down', function () {
    Event::fake([PurchaseVerificationDegraded::class]);
    bindVerifier(new StubVerifier(throws: true));

    $receipt = submit(submission(['body' => 'a sentence']));

    expect($receipt->verification)->toBe(VerificationState::Unknown)
        ->and(Expression::query()->count())->toBe(1);

    Event::assertDispatched(PurchaseVerificationDegraded::class);
});

it('never asks about history brought in from another platform', function () {
    $verifier = bindVerifier(new StubVerifier(new PurchaseConfirmation(true)));

    $receipt = submit(submission([
        'body' => 'a sentence',
        'source' => ExpressionSource::Syndicated,
        'sourceReference' => 'other:1',
    ]));

    expect($receipt->verification)->toBe(VerificationState::Unknown)
        ->and($verifier->asked)->toBe([]);
});

it('hands the verifier the two opaque references and nothing else', function () {
    $verifier = bindVerifier(new StubVerifier(new PurchaseConfirmation(true)));

    submit(submission(['body' => 'a sentence']));

    expect($verifier->asked)->toBe([['author' => AUTHOR, 'product' => PRODUCT]]);
});

/*
 * `unknown` is a state that has to survive the whole way to a badge. Flattening
 * it to `unverified` somewhere in a read model turns "we did not check" into "we
 * checked and they did not buy it" — which is the claim the host ships on every
 * row it has. Each projection is asserted separately because each is written
 * separately.
 */
it('does not flatten unknown into unverified on the way to any surface', function () {
    $receipt = submit(submission(['body' => 'a sentence']));
    approve($receipt->reference);

    $public = app(PublicReviewListing::class)(TENANT, PRODUCT);
    $staff = app(StaffExpression::class)(TENANT, $receipt->reference);
    $export = app(AuthorExport::class)(TENANT, AUTHOR);

    expect($public[0]->verification)->toBe(VerificationState::Unknown)
        ->and($public[0]->toArray()['verification'])->toBe('unknown')
        ->and($staff->verification)->toBe(VerificationState::Unknown)
        ->and($export[0]->verification)->toBe(VerificationState::Unknown);
});

it('carries verification across a revision rather than re-asking about the same purchase', function () {
    $verifier = bindVerifier(new StubVerifier(new PurchaseConfirmation(true)));

    $first = submit(submission(['body' => 'first']));
    $second = revise($first->reference, new ExpressionRevision(score: 5, scale: 5, body: 'second'));

    expect($second->verification)->toBe(VerificationState::Verified)
        ->and($verifier->asked)->toHaveCount(1);
});

it('leaves a merchant reply unbadged, because a reply is not a purchase', function () {
    bindVerifier(new StubVerifier(new PurchaseConfirmation(true)));

    $first = submit(submission(['body' => 'first']));

    expect(reply($first->reference)->verification)->toBe(VerificationState::Unknown);
});
