<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\FlagExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\FlagReason;
use Liberu\Ecommerce\ReviewsAndRatings\Events\ExpressionFlagged;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionNotFound;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Flag;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\FlagQueue;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\PublicReviewListing;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\StaffExpression;

beforeEach(function () {
    bindScreener();
    $this->receipt = submit(submission(['body' => 'a sentence']));
});

function flag(string $reference, string $reporter, FlagReason $reason = FlagReason::Spam): int
{
    return app(FlagExpression::class)(TENANT, $reference, $reporter, $reason);
}

it('records a report', function () {
    expect(flag($this->receipt->reference, 'shopper:zed'))->toBe(1);
});

it('does not hide anything just because somebody reported it', function () {
    approve($this->receipt->reference);
    flag($this->receipt->reference, 'shopper:zed');

    expect(app(PublicReviewListing::class)(TENANT, PRODUCT))->toHaveCount(1)
        ->and(app(StaffExpression::class)(TENANT, $this->receipt->reference)->displayState)->toBe(DisplayState::Approved);
});

it('counts one report per reporter however many times they press the button', function () {
    flag($this->receipt->reference, 'shopper:zed');
    flag($this->receipt->reference, 'shopper:zed', FlagReason::Profanity);

    expect(Flag::query()->count())->toBe(1)
        ->and(Flag::query()->sole()->reason)->toBe(FlagReason::Profanity);
});

it('shows a moderator how many people reported an expression', function () {
    flag($this->receipt->reference, 'shopper:one');
    flag($this->receipt->reference, 'shopper:two');

    expect(app(StaffExpression::class)(TENANT, $this->receipt->reference)->flagCount)->toBe(2);
});

it('lists reports for staff with their closed-enum reasons', function () {
    flag($this->receipt->reference, 'shopper:one', FlagReason::PersonalInformation);

    $queue = app(FlagQueue::class)(TENANT);

    expect($queue)->toHaveCount(1)
        ->and($queue[0]->reason)->toBe(FlagReason::PersonalInformation)
        ->and($queue[0]->expressionReference)->toBe($this->receipt->reference);
});

it('announces a report with the count that followed it', function () {
    Event::fake([ExpressionFlagged::class]);

    flag($this->receipt->reference, 'shopper:zed');

    Event::assertDispatched(ExpressionFlagged::class, fn (ExpressionFlagged $event): bool => $event->flagCount === 1);
});

it('refuses a report against an expression nobody has heard of', function () {
    expect(fn () => flag('00000000000000000000000000000000', 'shopper:zed'))->toThrow(ExpressionNotFound::class);
});
