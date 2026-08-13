<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Support;

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VerificationState;

/** What the module concluded about a purchase, and when it was told. */
final readonly class VerificationResult
{
    public function __construct(
        public VerificationState $state,
        public ?CarbonImmutable $confirmedAt = null,
    ) {}
}
