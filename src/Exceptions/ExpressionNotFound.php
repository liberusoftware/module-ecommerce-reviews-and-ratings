<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Exceptions;

/** 404. No expression with that reference in that tenant. */
final class ExpressionNotFound extends ReviewsAndRatingsException
{
    public static function withReference(string $reference): self
    {
        return new self('expression_not_found', "No expression [{$reference}].");
    }

    public function status(): int
    {
        return 404;
    }
}
