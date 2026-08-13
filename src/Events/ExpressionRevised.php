<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Events;

/**
 * An author edited. The old expression is still there and still says what it
 * said; this names both ends of the chain.
 */
final readonly class ExpressionRevised
{
    public function __construct(
        public string $tenantId,
        public string $reference,
        public string $supersededReference,
        public bool $supersededWasDisplayed,
    ) {}
}
