<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Exceptions;

/** 422. The submission cannot become a record as written. */
final class InvalidExpression extends ReviewsAndRatingsException
{
    public static function because(string $reason): self
    {
        return new self('invalid_expression', $reason);
    }

    public function status(): int
    {
        return 422;
    }
}
