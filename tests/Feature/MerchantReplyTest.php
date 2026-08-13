<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\RecordMerchantReply;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionRevision;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ReplySubmission;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionKind;
use Liberu\Ecommerce\ReviewsAndRatings\Events\MerchantReplied;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\DuplicateExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionAlreadySuperseded;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionNotFound;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\InvalidExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\PublicReviewListing;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\StaffExpression;

beforeEach(function () {
    bindScreener();
    $this->review = submit(submission(['body' => 'It broke.']));
});

it('records a reply as an expression in its own right, pending like any other', function () {
    $receipt = reply($this->review->reference);

    expect($receipt->displayState)->toBe(DisplayState::Pending)
        ->and(Expression::query()->where('reference', $receipt->reference)->sole()->kind)->toBe(ExpressionKind::MerchantReply);
});

it('hangs a reply off the expression rather than off the product', function () {
    reply($this->review->reference);

    $replyRow = Expression::query()->where('kind', ExpressionKind::MerchantReply->value)->sole();

    expect($replyRow->product_reference)->toBeNull()
        ->and($replyRow->parent_expression_id)->not->toBeNull();
});

it('never lets a reply inherit the shopper identity', function () {
    reply($this->review->reference);

    $replyRow = Expression::query()->where('kind', ExpressionKind::MerchantReply->value)->sole();

    expect($replyRow->author_reference)->toBe('merchant:support-desk')
        ->and($replyRow->author_reference)->not->toBe(AUTHOR)
        ->and($replyRow->author_display_name)->toBe('Acme Support');
});

it('allows at most one live reply per expression', function () {
    reply($this->review->reference);

    expect(fn () => reply($this->review->reference))->toThrow(DuplicateExpression::class);
});

it('lets a merchant edit a reply by superseding it', function () {
    $first = reply($this->review->reference, 'first answer');
    $second = revise($first->reference, new ExpressionRevision(body: 'second answer'));

    expect(Expression::query()->where('kind', ExpressionKind::MerchantReply->value)->count())->toBe(2)
        ->and($second->supersedesReference)->toBe($first->reference);
});

it('shows only an approved reply to a stranger', function () {
    approve($this->review->reference);
    $replyReceipt = reply($this->review->reference);

    expect(app(PublicReviewListing::class)(TENANT, PRODUCT)[0]->reply)->toBeNull();

    approve($replyReceipt->reference);

    expect(app(PublicReviewListing::class)(TENANT, PRODUCT)[0]->reply?->body)->toBe('Sorry to hear that — we have sent a replacement.');
});

it('refuses a reply to a reply', function () {
    $replyReceipt = reply($this->review->reference);

    expect(fn () => reply($replyReceipt->reference))->toThrow(InvalidExpression::class);
});

it('refuses a reply to something nobody has heard of', function () {
    expect(fn () => reply('00000000000000000000000000000000'))->toThrow(ExpressionNotFound::class);
});

it('refuses a reply to an expression that has since been edited', function () {
    revise($this->review->reference, new ExpressionRevision(score: 2, scale: 5, body: 'edited'));

    expect(fn () => reply($this->review->reference))->toThrow(ExpressionAlreadySuperseded::class);
});

it('refuses a reply with no body', function () {
    expect(fn () => app(RecordMerchantReply::class)(new ReplySubmission(
        tenantId: TENANT,
        parentReference: $this->review->reference,
        authorReference: 'merchant:support-desk',
        authorDisplayName: 'Acme Support',
        body: '   ',
    )))->toThrow(InvalidExpression::class);
});

it('refuses a reply with no display name for the merchant', function () {
    expect(fn () => app(RecordMerchantReply::class)(new ReplySubmission(
        tenantId: TENANT,
        parentReference: $this->review->reference,
        authorReference: 'merchant:support-desk',
        authorDisplayName: '',
        body: 'we are sorry',
    )))->toThrow(InvalidExpression::class);
});

it('announces a reply against the expression it answers', function () {
    Event::fake([MerchantReplied::class]);

    $receipt = reply($this->review->reference);

    Event::assertDispatched(MerchantReplied::class, fn (MerchantReplied $event): bool => $event->parentReference === $this->review->reference
        && $event->reference === $receipt->reference);
});

it('shows a reply in the staff view with the expression it answers', function () {
    $receipt = reply($this->review->reference);

    expect(app(StaffExpression::class)(TENANT, $receipt->reference)->parentReference)->toBe($this->review->reference);
});
