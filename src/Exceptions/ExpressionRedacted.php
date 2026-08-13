<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Exceptions;

/**
 * 410. The expression is still there — it still counts towards the published
 * aggregate and it still has its moderation history — but its content is gone
 * and nothing further may be written against it.
 */
final class ExpressionRedacted extends ReviewsAndRatingsException
{
    public static function withReference(string $reference): self
    {
        return new self('expression_redacted', "Expression [{$reference}] has been redacted.");
    }

    public function status(): int
    {
        return 410;
    }
}
