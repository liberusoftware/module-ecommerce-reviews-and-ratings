<?php

declare(strict_types=1);

use Liberu\Ecommerce\ReviewsAndRatings\Contracts\ScreensContent;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionRevision;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ScreeningOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ScreeningDisposition;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ScreeningPriority;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ScreeningUnavailable;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\ModerationQueue;
use Liberu\Ecommerce\ReviewsAndRatings\Queries\StaffExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Support\ExpressionWriter;
use Liberu\Ecommerce\ReviewsAndRatings\Tests\Support\RecordingScreener;

it('refuses free text with a 503 when no screener is bound', function () {
    expect(app()->bound(ScreensContent::class))->toBeFalse();

    $thrown = null;

    try {
        submit(submission(['body' => 'unscreened speech']));
    } catch (ScreeningUnavailable $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(ScreeningUnavailable::class)
        ->and($thrown->status())->toBe(503)
        ->and(Expression::query()->count())->toBe(0);
});

it('accepts a star with no words when no screener is bound, because there is nothing to screen', function () {
    $receipt = submit(submission(['body' => null]));

    expect($receipt->screeningPriority)->toBe(ScreeningPriority::Routine)
        ->and(Expression::query()->count())->toBe(1);
});

it('refuses with a 503 when the screener is bound and fails', function () {
    bindScreener(new RecordingScreener(throws: true));

    expect(fn () => submit(submission(['body' => 'a sentence'])))->toThrow(ScreeningUnavailable::class);
    expect(Expression::query()->count())->toBe(0);
});

it('hands the screener the body and the locale', function () {
    $screener = bindScreener();

    submit(submission(['body' => '  spaced out  ', 'locale' => 'fr']));

    expect($screener->seen)->toBe([['body' => 'spaced out', 'locale' => 'fr']]);
});

it('never lets a machine reject speech, only queue it for a person', function () {
    bindScreener(new RecordingScreener(ScreeningOutcome::escalate([ModerationReason::Profanity])));

    $receipt = submit(submission(['body' => 'a sentence']));
    $review = app(StaffExpression::class)(TENANT, $receipt->reference);

    expect($review->displayState)->toBe(DisplayState::Escalated)
        ->and($review->displayState->isDisplayed())->toBeFalse()
        ->and($review->screeningSignals)->toBe([ModerationReason::Profanity]);
});

it('attributes an escalation to the machine rather than to a person', function () {
    bindScreener(new RecordingScreener(ScreeningOutcome::escalate([ModerationReason::Spam])));

    $receipt = submit(submission(['body' => 'a sentence']));
    $history = app(StaffExpression::class)(TENANT, $receipt->reference)->moderationHistory;

    expect($history)->toHaveCount(1)
        ->and($history[0]->actorReference)->toBe(ExpressionWriter::SCREENING_ACTOR)
        ->and($history[0]->reason)->toBe(ModerationReason::MachineEscalation);
});

it('records a routine screening as a priority with no decision at all', function () {
    bindScreener();

    $receipt = submit(submission(['body' => 'a sentence']));
    $review = app(StaffExpression::class)(TENANT, $receipt->reference);

    expect($review->displayState)->toBe(DisplayState::Pending)
        ->and($review->moderationHistory)->toBe([]);
});

it('puts the urgent work at the top of the queue, which a string sort would not', function () {
    bindScreener(new RecordingScreener(new ScreeningOutcome(
        ScreeningDisposition::Queue,
        ScreeningPriority::Routine,
    )));
    submit(submission(['body' => 'routine', 'productReference' => 'catalogue:a']));

    bindScreener(new RecordingScreener(new ScreeningOutcome(
        ScreeningDisposition::Queue,
        ScreeningPriority::Elevated,
    )));
    submit(submission(['body' => 'elevated', 'productReference' => 'catalogue:b']));

    bindScreener(new RecordingScreener(new ScreeningOutcome(
        ScreeningDisposition::Queue,
        ScreeningPriority::Urgent,
    )));
    submit(submission(['body' => 'urgent', 'productReference' => 'catalogue:c']));

    $queue = app(ModerationQueue::class)(TENANT);

    expect(array_map(fn ($review) => $review->body, $queue))->toBe(['urgent', 'elevated', 'routine']);
});

it('screens a revision as well as a first submission', function () {
    $screener = bindScreener();

    $first = submit(submission(['body' => 'first']));
    revise($first->reference, new ExpressionRevision(score: 2, scale: 5, body: 'second'));

    expect($screener->seen)->toHaveCount(2);
});

it('screens a merchant reply as well as a shopper review', function () {
    $screener = bindScreener();

    $first = submit(submission(['body' => 'first']));
    reply($first->reference, 'we are sorry');

    expect($screener->seen)->toHaveCount(2);
});

it('lets a screener refuse in its own words without relabelling the failure', function () {
    bindScreener(new class() implements ScreensContent
    {
        public function screen(string $body, string $locale): ScreeningOutcome
        {
            throw ScreeningUnavailable::unbound();
        }
    });

    $thrown = null;

    try {
        submit(submission(['body' => 'a sentence']));
    } catch (ScreeningUnavailable $exception) {
        $thrown = $exception;
    }

    expect($thrown?->errorCode)->toBe('screening_unavailable');
});
