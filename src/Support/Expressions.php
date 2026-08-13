<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Support;

use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionNotFound;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;

/** Lookup by public reference, always scoped to a tenant. */
final class Expressions
{
    /** @param  list<string>  $with */
    public static function locate(string $tenantId, string $reference, array $with = []): Expression
    {
        $expression = Expression::query()
            ->with($with)
            ->where('tenant_id', $tenantId)
            ->where('reference', $reference)
            ->first();

        if (! $expression instanceof Expression) {
            throw ExpressionNotFound::withReference($reference);
        }

        return $expression;
    }
}
