<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Queries;

use Liberu\Ecommerce\ReviewsAndRatings\Data\RatingAggregate;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\AggregatePopulation;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionKind;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;

/**
 * Everything recorded, whatever anyone decided about it. **Staff only.**
 *
 * Named differently from the displayed figure on purpose: two aggregates over
 * the same rows with the same name is how a total including withheld content
 * ends up on a product page.
 */
final class RecordedRatingTotal
{
    public function __invoke(string $tenantId, string $productReference, int $scale = 5): RatingAggregate
    {
        $query = Expression::query()
            ->where('tenant_id', $tenantId)
            ->where('product_reference', $productReference)
            ->where('kind', ExpressionKind::ShopperReview->value)
            ->where('scale', $scale)
            ->whereNotNull('score')
            ->whereNull('superseded_at');

        return new RatingAggregate(
            sum: (int) $query->clone()->sum('score'),
            count: $query->clone()->count(),
            scale: $scale,
            population: AggregatePopulation::Recorded,
        );
    }
}
