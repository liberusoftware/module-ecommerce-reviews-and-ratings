<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Data;

use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ScreeningDisposition;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ScreeningPriority;

/**
 * What automated screening found.
 *
 * There is no "reject" disposition. Screening routes an expression to a human
 * queue, urgently or not; it never decides.
 */
final readonly class ScreeningOutcome
{
    /** @param  list<ModerationReason>  $signals */
    public function __construct(
        public ScreeningDisposition $disposition,
        public ScreeningPriority $priority,
        public array $signals = [],
    ) {}

    public static function clean(): self
    {
        return new self(ScreeningDisposition::Queue, ScreeningPriority::Routine);
    }

    /** @param  list<ModerationReason>  $signals */
    public static function escalate(array $signals, ScreeningPriority $priority = ScreeningPriority::Urgent): self
    {
        return new self(ScreeningDisposition::Escalate, $priority, $signals);
    }

    /** @return list<string> */
    public function signalValues(): array
    {
        return array_map(static fn (ModerationReason $reason): string => $reason->value, $this->signals);
    }
}
