<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Events;

/**
 * The purchase seam was bound and threw.
 *
 * The write still succeeded — refusing speech because a service is down is
 * worse than publishing it unbadged — so this is the only signal that a badge
 * is missing for an operational reason rather than a factual one.
 */
final readonly class PurchaseVerificationDegraded
{
    public function __construct(
        public string $tenantId,
        public string $productReference,
        public string $reason,
    ) {}
}
