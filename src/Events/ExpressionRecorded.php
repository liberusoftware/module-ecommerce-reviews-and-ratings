<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Events;

use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionKind;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VerificationState;

/** Somebody said something. It is recorded; it is not displayed. */
final readonly class ExpressionRecorded
{
    public function __construct(
        public string $tenantId,
        public string $reference,
        public ExpressionKind $kind,
        public ?string $productReference,
        public VerificationState $verification,
    ) {}
}
