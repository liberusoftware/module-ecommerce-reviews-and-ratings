<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Events;

use Liberu\Ecommerce\ReviewsAndRatings\Enums\FlagReason;

/** A reader reported an expression. Nothing is hidden by being reported. */
final readonly class ExpressionFlagged
{
    public function __construct(
        public string $tenantId,
        public string $expressionReference,
        public FlagReason $reason,
        public int $flagCount,
    ) {}
}
