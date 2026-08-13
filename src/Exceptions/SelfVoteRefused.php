<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Exceptions;

/**
 * 403. An author may not vote on their own expression.
 *
 * Refused with its own exception rather than silently ignored: a caller that
 * cannot tell the difference between "counted" and "quietly dropped" will build
 * a UI that lies about it.
 */
final class SelfVoteRefused extends ReviewsAndRatingsException
{
    public static function withReference(string $reference): self
    {
        return new self('self_vote_refused', "An author cannot vote on their own expression [{$reference}].");
    }

    public function status(): int
    {
        return 403;
    }
}
