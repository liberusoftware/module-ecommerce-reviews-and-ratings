<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Contracts;

use Liberu\Ecommerce\ReviewsAndRatings\Data\PurchaseConfirmation;

/**
 * The seam to whatever knows about orders.
 *
 * Bind an implementation to give shoppers a verified-purchase badge. Leaving it
 * unbound is a valid deployment — a merchant with no order history wired up, or
 * one that does not want the badge — and produces `VerificationState::Unknown`,
 * which is not `Unverified` and must not be flattened into it anywhere between
 * here and the badge.
 *
 * Resolve it optionally, with a `= null` default: the container only falls back
 * to a default when the parameter has one, so dropping it turns an unbound seam
 * into a BindingResolutionException.
 */
interface ConfirmsPurchase
{
    /** Did this author buy this product? Null when the question cannot be answered. */
    public function confirm(string $authorReference, string $productReference): ?PurchaseConfirmation;
}
