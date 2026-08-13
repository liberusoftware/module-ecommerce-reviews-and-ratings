<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Data;

use Carbon\CarbonImmutable;

/**
 * A merchant's answer to one expression.
 *
 * It carries its own author reference and its own display name. A reply is
 * never anonymous and never inherits the shopper's identity.
 */
final readonly class ReplySubmission
{
    public function __construct(
        public string $tenantId,
        public string $parentReference,
        public string $authorReference,
        public string $authorDisplayName,
        public string $body,
        public string $locale = 'en',
        public ?CarbonImmutable $occurredAt = null,
    ) {}
}
