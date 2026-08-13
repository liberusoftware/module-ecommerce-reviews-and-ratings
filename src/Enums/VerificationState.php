<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Enums;

/**
 * Whether the author bought the thing.
 *
 * Tri-state, not a boolean, because the absence of a badge is itself a claim
 * shown to a shopper: `Unverified` says "we checked and they did not buy it",
 * and `Unknown` says "we could not ask". A surface renders a badge for
 * `Verified`, renders nothing at all for `Unknown`, and only renders a negative
 * for `Unverified`.
 */
enum VerificationState: string
{
    case Verified = 'verified';
    case Unverified = 'unverified';
    case Unknown = 'unknown';
}
