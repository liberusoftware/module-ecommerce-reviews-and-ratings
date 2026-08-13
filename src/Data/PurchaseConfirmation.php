<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Data;

use Carbon\CarbonImmutable;

/** What a purchase verifier answers with. */
final readonly class PurchaseConfirmation
{
    public function __construct(
        public bool $purchased,
        public ?CarbonImmutable $confirmedAt = null,
    ) {}
}
