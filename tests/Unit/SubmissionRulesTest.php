<?php

declare(strict_types=1);

use Liberu\Ecommerce\ReviewsAndRatings\Data\FacetSubmission;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\FacetKind;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\InvalidExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Support\SubmissionRules;

it('publishes one body limit rather than leaving every surface to invent its own', function () {
    expect(SubmissionRules::MAX_BODY_LENGTH)->toBe(5000)
        ->and(SubmissionRules::MAX_DISPLAY_NAME_LENGTH)->toBe(80);
});

it('accepts a body exactly at the limit and refuses one character more', function () {
    SubmissionRules::assertBody(str_repeat('a', SubmissionRules::MAX_BODY_LENGTH));

    expect(fn () => SubmissionRules::assertBody(str_repeat('a', SubmissionRules::MAX_BODY_LENGTH + 1)))
        ->toThrow(InvalidExpression::class);
});

it('measures a body in characters, not in bytes', function () {
    SubmissionRules::assertBody(str_repeat('é', SubmissionRules::MAX_BODY_LENGTH));
})->throwsNoExceptions();

it('accepts a display name at the limit and refuses one longer', function () {
    SubmissionRules::assertDisplayName(str_repeat('n', SubmissionRules::MAX_DISPLAY_NAME_LENGTH));

    expect(fn () => SubmissionRules::assertDisplayName(str_repeat('n', SubmissionRules::MAX_DISPLAY_NAME_LENGTH + 1)))
        ->toThrow(InvalidExpression::class);
});

it('accepts the smallest scale anyone could interpret', function () {
    SubmissionRules::assertContent(1, SubmissionRules::MIN_SCALE, null, []);
})->throwsNoExceptions();

it('accepts a zero score, which is a rating and not an absence', function () {
    SubmissionRules::assertContent(0, 5, null, []);
})->throwsNoExceptions();

it('refuses a scale beyond anything a surface could render', function () {
    expect(fn () => SubmissionRules::assertContent(1, SubmissionRules::MAX_SCALE + 1, null, []))
        ->toThrow(InvalidExpression::class);
});

it('refuses a negative score', function () {
    expect(fn () => SubmissionRules::assertContent(-1, 5, null, []))->toThrow(InvalidExpression::class);
});

it('names the facet it is complaining about', function () {
    $thrown = null;

    try {
        SubmissionRules::assertContent(4, 5, null, [new FacetSubmission(FacetKind::Delivery, 99, 5)]);
    } catch (InvalidExpression $exception) {
        $thrown = $exception;
    }

    expect($thrown?->getMessage())->toContain('delivery');
});
