<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionRevision;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Events\ModerationDecided;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionNotFound;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Liberu\Ecommerce\ReviewsAndRatings\Models\ModerationDecision;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\ModerationQueue;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\StaffExpression;

beforeEach(function () {
    bindScreener();
});

it('derives the display state from the latest decision rather than a stored flag', function () {
    $receipt = submit(submission(['body' => 'a sentence']));

    expect(app(StaffExpression::class)(TENANT, $receipt->reference)->displayState)->toBe(DisplayState::Pending);

    approve($receipt->reference);

    expect(app(StaffExpression::class)(TENANT, $receipt->reference)->displayState)->toBe(DisplayState::Approved);
});

it('keeps every decision, so approved-retracted-reapproved does not look like approved once', function () {
    $receipt = submit(submission(['body' => 'a sentence']));

    decide($receipt->reference, ModerationOutcome::Approved, ModerationReason::Compliant, 'staff:ana');
    decide($receipt->reference, ModerationOutcome::Withheld, ModerationReason::OffTopic, 'staff:ben');
    decide($receipt->reference, ModerationOutcome::Approved, ModerationReason::Compliant, 'staff:cara');

    $review = app(StaffExpression::class)(TENANT, $receipt->reference);

    expect($review->moderationHistory)->toHaveCount(3)
        ->and(array_map(fn ($record) => $record->actorReference, $review->moderationHistory))
        ->toBe(['staff:ana', 'staff:ben', 'staff:cara'])
        ->and($review->displayState)->toBe(DisplayState::Approved);
});

it('names an actor on every decision', function () {
    $receipt = submit(submission(['body' => 'a sentence']));

    $record = decide($receipt->reference, ModerationOutcome::Rejected, ModerationReason::Spam, 'staff:dee');

    expect($record->actorReference)->toBe('staff:dee')
        ->and($record->reason)->toBe(ModerationReason::Spam)
        ->and($record->outcome)->toBe(ModerationOutcome::Rejected);
});

it('carries no free text alongside a decision', function () {
    $receipt = submit(submission(['body' => 'a sentence']));
    approve($receipt->reference);

    $columns = array_keys(ModerationDecision::query()->sole()->getAttributes());

    expect($columns)->not->toContain('note')
        ->and($columns)->not->toContain('comment');
});

it('announces a decision so a cache of published figures can listen', function () {
    Event::fake([ModerationDecided::class]);

    $receipt = submit(submission(['body' => 'a sentence']));
    approve($receipt->reference);

    Event::assertDispatched(ModerationDecided::class, fn (ModerationDecided $event): bool => $event->record->outcome === ModerationOutcome::Approved);
});

it('refuses to decide about an expression nobody has heard of', function () {
    expect(fn () => approve('00000000000000000000000000000000'))->toThrow(ExpressionNotFound::class);
});

it('lists the pending queue by default', function () {
    $pending = submit(submission(['body' => 'waiting']));
    $decided = submit(submission(['productReference' => 'catalogue:other', 'body' => 'decided']));
    approve($decided->reference);

    $queue = app(ModerationQueue::class)(TENANT);

    expect($queue)->toHaveCount(1)
        ->and($queue[0]->reference)->toBe($pending->reference);
});

it('lists a decided state on request', function () {
    $decided = submit(submission(['body' => 'decided']));
    decide($decided->reference, ModerationOutcome::Withheld, ModerationReason::OffTopic);

    expect(app(ModerationQueue::class)(TENANT, DisplayState::Withheld))->toHaveCount(1);
});

it('lists every live expression when asked for no state at all', function () {
    submit(submission(['body' => 'one']));
    $two = submit(submission(['productReference' => 'catalogue:other', 'body' => 'two']));
    approve($two->reference);

    expect(app(ModerationQueue::class)(TENANT, null))->toHaveCount(2);
});

it('keeps one tenant out of another tenant queue', function () {
    submit(submission(['body' => 'ours']));
    submit(submission(['tenantId' => 'tenant-beta', 'body' => 'theirs']));

    expect(app(ModerationQueue::class)(TENANT))->toHaveCount(1);
});

it('tells a moderator when they are looking at an edit of something they already approved', function () {
    $first = submit(submission(['body' => 'first']));
    approve($first->reference);
    $second = revise($first->reference, new ExpressionRevision(score: 1, scale: 5, body: 'second'));

    $review = app(StaffExpression::class)(TENANT, $second->reference);

    expect($review->editedAfterApproval)->toBeTrue()
        ->and($review->supersedesReference)->toBe($first->reference);
});

it('does not claim an edit followed an approval when it did not', function () {
    $first = submit(submission(['body' => 'first']));
    $second = revise($first->reference, new ExpressionRevision(score: 1, scale: 5, body: 'second'));

    expect(app(StaffExpression::class)(TENANT, $second->reference)->editedAfterApproval)->toBeFalse();
});

it('leaves a superseded expression out of the queue', function () {
    $first = submit(submission(['body' => 'first']));
    revise($first->reference, new ExpressionRevision(score: 1, scale: 5, body: 'second'));

    $queue = app(ModerationQueue::class)(TENANT);

    expect($queue)->toHaveCount(1)
        ->and($queue[0]->isSuperseded)->toBeFalse();
});

it('refuses to show a staff view of an expression nobody has heard of', function () {
    expect(fn () => app(StaffExpression::class)(TENANT, 'nope'))->toThrow(ExpressionNotFound::class);
});

it('scopes a staff view to its tenant', function () {
    $receipt = submit(submission(['body' => 'ours']));

    expect(fn () => app(StaffExpression::class)('tenant-beta', $receipt->reference))->toThrow(ExpressionNotFound::class);
    expect(Expression::query()->count())->toBe(1);
});
