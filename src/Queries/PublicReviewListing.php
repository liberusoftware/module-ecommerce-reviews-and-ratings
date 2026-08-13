<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Queries;

use Illuminate\Database\Eloquent\Builder;
use Liberu\Ecommerce\ReviewsAndRatings\Data\PublicReview;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionKind;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Liberu\Ecommerce\ReviewsAndRatings\Support\ReadModels;

/**
 * What a stranger may see about a product.
 *
 * Everything here traces to a moderation decision that names an actor. An
 * expression with no decision is not in this list, a superseded one is not in
 * this list, and a redacted one is not in this list — while still counting
 * towards the published aggregate.
 */
final class PublicReviewListing
{
    /** @return list<PublicReview> */
    public function __invoke(string $tenantId, string $productReference, int $limit = 20, int $offset = 0): array
    {
        $approved = static fn (Builder $query): Builder => $query->whereHas(
            'latestDecision',
            static fn (Builder $decision): Builder => $decision->where('outcome', ModerationOutcome::Approved->value),
        );

        return Expression::query()
            ->where('tenant_id', $tenantId)
            ->where('product_reference', $productReference)
            ->where('kind', ExpressionKind::ShopperReview->value)
            ->whereNull('superseded_at')
            ->whereNull('redacted_at')
            ->tap($approved)
            ->with([
                'facets',
                'votes',
                'replies' => static fn ($query) => $query
                    ->whereNull('superseded_at')
                    ->whereNull('redacted_at')
                    ->tap($approved),
            ])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(ReadModels::publicReview(...))
            ->values()
            ->all();
    }
}
