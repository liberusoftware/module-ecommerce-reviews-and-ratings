<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Queries;

use Illuminate\Database\Eloquent\Builder;
use Liberu\Ecommerce\ReviewsAndRatings\Data\RatingAggregate;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\AggregatePopulation;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionKind;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;

/**
 * The one aggregate a shopper may be shown.
 *
 * It counts scores whose latest moderation decision is `approved` and nothing
 * else, which is what closes the host's bypass: there, a star landed in the
 * public average the moment it was posted while the sentence beside it waited
 * for a moderator, so the moderation control did not cover the number actually
 * displayed.
 *
 * A redacted expression is still counted. Erasure removes a person from a page;
 * it does not retroactively change a figure that has already been published.
 *
 * Scale is a parameter, not a guess. A four out of five and a four out of ten
 * are not summable, and an aggregate that quietly mixed them would be a number
 * with no meaning.
 */
final class DisplayedRatingAggregate
{
    public function __invoke(string $tenantId, string $productReference, int $scale = 5): RatingAggregate
    {
        $query = Expression::query()
            ->where('tenant_id', $tenantId)
            ->where('product_reference', $productReference)
            ->where('kind', ExpressionKind::ShopperReview->value)
            ->where('scale', $scale)
            ->whereNotNull('score')
            ->whereNull('superseded_at')
            ->whereHas(
                'latestDecision',
                static fn (Builder $decision): Builder => $decision->where('outcome', ModerationOutcome::Approved->value),
            );

        return new RatingAggregate(
            sum: (int) $query->clone()->sum('score'),
            count: $query->clone()->count(),
            scale: $scale,
            population: AggregatePopulation::Displayed,
        );
    }
}
