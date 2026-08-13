<?php

declare(strict_types=1);

use Liberu\Ecommerce\ReviewsAndRatings\Data\ScreeningOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\AggregatePopulation;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionSource;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\IncentiveDisclosure;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ScreeningDisposition;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ScreeningPriority;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VerificationState;

it('displays approved and nothing else', function (ModerationOutcome $outcome, bool $displayed) {
    expect($outcome->displayState()->isDisplayed())->toBe($displayed);
})->with([
    [ModerationOutcome::Approved, true],
    [ModerationOutcome::Rejected, false],
    [ModerationOutcome::Withheld, false],
    [ModerationOutcome::Escalated, false],
]);

it('does not display an expression nobody has decided about', function () {
    expect(DisplayState::Pending->isDisplayed())->toBeFalse();
});

it('only offers to check a purchase this merchant could know about', function () {
    expect(ExpressionSource::FirstParty->isVerifiable())->toBeTrue()
        ->and(ExpressionSource::Syndicated->isVerifiable())->toBeFalse()
        ->and(ExpressionSource::Imported->isVerifiable())->toBeFalse();
});

it('keeps the three verification states distinct, because a missing badge is a claim', function () {
    expect(VerificationState::cases())->toHaveCount(3)
        ->and(VerificationState::Unknown)->not->toBe(VerificationState::Unverified);
});

it('sorts screening urgency by weight, which a string sort would get wrong', function () {
    $labels = array_map(fn (ScreeningPriority $priority): string => $priority->value, ScreeningPriority::cases());
    sort($labels);

    expect(ScreeningPriority::Urgent->weight())->toBeGreaterThan(ScreeningPriority::Elevated->weight())
        ->and(ScreeningPriority::Elevated->weight())->toBeGreaterThan(ScreeningPriority::Routine->weight())
        ->and($labels)->toBe(['elevated', 'routine', 'urgent']);
});

it('offers screening no way to reject anything', function () {
    expect(array_map(fn (ScreeningDisposition $case): string => $case->value, ScreeningDisposition::cases()))
        ->toBe(['queue', 'escalate']);
});

it('reports no incentive as no incentive', function () {
    expect(IncentiveDisclosure::None->isDisclosed())->toBeFalse()
        ->and(IncentiveDisclosure::Payment->isDisclosed())->toBeTrue();
});

it('names the two populations differently so neither can be shown as the other', function () {
    expect(AggregatePopulation::Displayed->value)->not->toBe(AggregatePopulation::Recorded->value);
});

it('builds a clean screening outcome that queues at routine priority', function () {
    $outcome = ScreeningOutcome::clean();

    expect($outcome->disposition)->toBe(ScreeningDisposition::Queue)
        ->and($outcome->priority)->toBe(ScreeningPriority::Routine)
        ->and($outcome->signals)->toBe([]);
});

it('carries screening signals as closed enum values', function () {
    $outcome = ScreeningOutcome::escalate([ModerationReason::Profanity, ModerationReason::PersonalInformation]);

    expect($outcome->disposition)->toBe(ScreeningDisposition::Escalate)
        ->and($outcome->priority)->toBe(ScreeningPriority::Urgent)
        ->and($outcome->signalValues())->toBe(['profanity', 'personal_information']);
});
