<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Exceptions;

/**
 * 409, permanently. The expression has already been edited; the caller is
 * holding a stale reference and must re-read the chain.
 */
final class ExpressionAlreadySuperseded extends ReviewsAndRatingsException
{
    public static function withReference(string $reference): self
    {
        return new self('expression_already_superseded', "Expression [{$reference}] has already been superseded and cannot be revised again.");
    }

    public function status(): int
    {
        return 409;
    }
}
