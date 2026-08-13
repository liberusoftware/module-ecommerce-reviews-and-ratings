<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Data;

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionKind;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionSource;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\IncentiveDisclosure;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ScreeningPriority;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VerificationState;

/**
 * The staff projection: written independently of the public one, and carrying
 * the things a moderator has to have — who wrote it, what the machine flagged,
 * every decision anyone has made, and whether this is an edit of something that
 * was already approved.
 */
final readonly class StaffReview
{
    /**
     * @param  list<FacetScore>  $facets
     * @param  list<ModerationReason>  $screeningSignals
     * @param  list<ModerationRecord>  $moderationHistory
     */
    public function __construct(
        public string $reference,
        public string $tenantId,
        public ExpressionKind $kind,
        public ?string $productReference,
        public ?string $parentReference,
        public ?string $authorReference,
        public ?string $authorDisplayName,
        public ?int $score,
        public ?int $scale,
        public ?string $body,
        public string $locale,
        public ExpressionSource $source,
        public ?string $sourceReference,
        public IncentiveDisclosure $incentive,
        public VerificationState $verification,
        public DisplayState $displayState,
        public ScreeningPriority $screeningPriority,
        public array $screeningSignals,
        public array $facets,
        public VoteTally $votes,
        public int $flagCount,
        public array $moderationHistory,
        public ?string $supersedesReference,
        public bool $editedAfterApproval,
        public bool $isSuperseded,
        public bool $isRedacted,
        public CarbonImmutable $occurredAt,
        public CarbonImmutable $recordedAt,
    ) {}
}
