<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Support;

use Liberu\Ecommerce\ReviewsAndRatings\Data\FacetSubmission;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\InvalidExpression;

/**
 * One set of limits, in one place, that every surface reads.
 *
 * The host has three disagreeing answers for how long a review body may be — a
 * form request at 1000, a Filament textarea at 65535, and a `text` column — so
 * the limit a shopper hits is not the limit an operator hits. These constants
 * are public because a form that does not read them will invent a fourth.
 */
final class SubmissionRules
{
    public const int MAX_BODY_LENGTH = 5000;

    public const int MAX_DISPLAY_NAME_LENGTH = 80;

    public const int MIN_SCALE = 2;

    public const int MAX_SCALE = 100;

    /** @param  list<FacetSubmission>  $facets */
    public static function assertContent(?int $score, ?int $scale, ?string $body, array $facets): void
    {
        if (($score === null) !== ($scale === null)) {
            throw InvalidExpression::because('A score must carry the scale it was given on, and a scale means nothing without a score.');
        }

        if ($score === null && $body === null && $facets === []) {
            throw InvalidExpression::because('An expression must carry a score, a body, or both.');
        }

        if ($score !== null && $scale !== null) {
            self::assertScore($score, $scale, 'score');
        }

        if ($body !== null) {
            self::assertBody($body);
        }

        self::assertFacets($facets);
    }

    public static function assertBody(string $body): void
    {
        if (trim($body) === '') {
            throw InvalidExpression::because('A body that is only whitespace is not an expression; omit it instead.');
        }

        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            throw InvalidExpression::because('A body may not exceed '.self::MAX_BODY_LENGTH.' characters.');
        }
    }

    public static function assertDisplayName(string $name): void
    {
        if (trim($name) === '') {
            throw InvalidExpression::because('An expression must carry the display name its author chose for it.');
        }

        if (mb_strlen($name) > self::MAX_DISPLAY_NAME_LENGTH) {
            throw InvalidExpression::because('A display name may not exceed '.self::MAX_DISPLAY_NAME_LENGTH.' characters.');
        }
    }

    /** @param  list<FacetSubmission>  $facets */
    private static function assertFacets(array $facets): void
    {
        $kinds = [];

        foreach ($facets as $facet) {
            if (in_array($facet->kind->value, $kinds, true)) {
                throw InvalidExpression::because("An expression carries the [{$facet->kind->value}] facet twice.");
            }

            $kinds[] = $facet->kind->value;

            self::assertScore($facet->score, $facet->scale, $facet->kind->value);
        }
    }

    private static function assertScore(int $score, int $scale, string $label): void
    {
        if ($scale < self::MIN_SCALE || $scale > self::MAX_SCALE) {
            throw InvalidExpression::because("A [{$label}] scale must be between ".self::MIN_SCALE.' and '.self::MAX_SCALE.'.');
        }

        if ($score < 0 || $score > $scale) {
            throw InvalidExpression::because("A [{$label}] of {$score} is not on a scale of {$scale}.");
        }
    }
}
