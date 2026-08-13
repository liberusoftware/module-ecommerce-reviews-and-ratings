<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Data;

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\FlagReason;

/** A reader's report about an expression. Staff-only. */
final readonly class FlagRecord
{
    public function __construct(
        public string $expressionReference,
        public FlagReason $reason,
        public ?string $reporterReference,
        public CarbonImmutable $occurredAt,
    ) {}
}
