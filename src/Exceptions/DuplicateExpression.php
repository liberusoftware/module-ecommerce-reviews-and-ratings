<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Exceptions;

/**
 * 409, permanently. This author already has a live expression on this product,
 * or this expression already has a live merchant reply.
 *
 * Raised from a unique-index violation, not from a check-then-act read, so two
 * concurrent writers cannot both win.
 */
final class DuplicateExpression extends ReviewsAndRatingsException
{
    public static function onProduct(string $productReference): self
    {
        return new self('duplicate_expression', "This author already has a live expression on product [{$productReference}]; revise it instead.");
    }

    public static function onReply(string $parentReference): self
    {
        return new self('duplicate_reply', "Expression [{$parentReference}] already has a live merchant reply; revise it instead.");
    }

    public function status(): int
    {
        return 409;
    }
}
