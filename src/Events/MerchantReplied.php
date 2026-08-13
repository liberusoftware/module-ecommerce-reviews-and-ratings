<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Events;

/** A merchant answered one expression. The reply is itself pending moderation. */
final readonly class MerchantReplied
{
    public function __construct(
        public string $tenantId,
        public string $reference,
        public string $parentReference,
    ) {}
}
