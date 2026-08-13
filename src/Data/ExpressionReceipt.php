<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Data;

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ScreeningPriority;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VerificationState;

/**
 * What a write action hands back.
 *
 * Actions return this rather than an Eloquent model, so an adapter can type
 * against the module's surface without importing its persistence.
 */
final readonly class ExpressionReceipt
{
    public function __construct(
        public string $reference,
        public DisplayState $displayState,
        public VerificationState $verification,
        public ScreeningPriority $screeningPriority,
        public ?string $supersedesReference,
        public CarbonImmutable $occurredAt,
        public CarbonImmutable $recordedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'display_state' => $this->displayState->value,
            'verification' => $this->verification->value,
            'screening_priority' => $this->screeningPriority->value,
            'supersedes_reference' => $this->supersedesReference,
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'recorded_at' => $this->recordedAt->toIso8601String(),
        ];
    }
}
