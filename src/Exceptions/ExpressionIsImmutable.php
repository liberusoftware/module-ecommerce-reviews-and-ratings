<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Exceptions;

/**
 * 409. Something tried to rewrite a historical fact.
 *
 * An expression is append-only: an edit is a new row that supersedes the old
 * one, never a changed row. The only updates a recorded expression accepts are
 * being superseded, being redacted, and being re-prioritised in the queue.
 */
final class ExpressionIsImmutable extends ReviewsAndRatingsException
{
    public static function column(string $reference, string $column): self
    {
        return new self('expression_is_immutable', "Expression [{$reference}] is append-only; [{$column}] cannot be changed. Record a revision instead.");
    }

    public function status(): int
    {
        return 409;
    }
}
