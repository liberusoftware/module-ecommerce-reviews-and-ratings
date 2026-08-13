<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Data;

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionSource;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\IncentiveDisclosure;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VerificationState;

/**
 * One expression as returned to the person who wrote it.
 *
 * Export carries the moderation state and the reasons: a review held pending is
 * data held about a person that no page shows them, and a subject access
 * request that omits it is incomplete.
 */
final readonly class AuthorExportRecord
{
    /**
     * @param  list<FacetScore>  $facets
     * @param  list<ModerationRecord>  $moderationHistory
     */
    public function __construct(
        public string $reference,
        public ?string $productReference,
        public ?int $score,
        public ?int $scale,
        public ?string $body,
        public ?string $authorDisplayName,
        public string $locale,
        public ExpressionSource $source,
        public IncentiveDisclosure $incentive,
        public VerificationState $verification,
        public DisplayState $displayState,
        public array $facets,
        public array $moderationHistory,
        public bool $isSuperseded,
        public bool $isRedacted,
        public CarbonImmutable $occurredAt,
        public CarbonImmutable $recordedAt,
    ) {}
}
