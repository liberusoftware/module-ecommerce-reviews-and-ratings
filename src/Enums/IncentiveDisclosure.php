<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Enums;

/** What the author was given, if anything, in exchange for the expression. */
enum IncentiveDisclosure: string
{
    case None = 'none';
    case FreeProduct = 'free_product';
    case Discount = 'discount';
    case SweepstakesEntry = 'sweepstakes_entry';
    case Payment = 'payment';
    case LoyaltyPoints = 'loyalty_points';

    public function isDisclosed(): bool
    {
        return $this !== self::None;
    }
}
