<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Data;

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;

/** One decision, naming who made it. Staff-only; never reaches a shopper. */
final readonly class ModerationRecord
{
    public function __construct(
        public string $expressionReference,
        public ModerationOutcome $outcome,
        public ModerationReason $reason,
        public string $actorReference,
        public CarbonImmutable $occurredAt,
        public CarbonImmutable $recordedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'expression_reference' => $this->expressionReference,
            'outcome' => $this->outcome->value,
            'reason' => $this->reason->value,
            'actor_reference' => $this->actorReference,
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'recorded_at' => $this->recordedAt->toIso8601String(),
        ];
    }
}
