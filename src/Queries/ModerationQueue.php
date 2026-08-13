<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Queries;

use Illuminate\Database\Eloquent\Builder;
use Liberu\Ecommerce\ReviewsAndRatings\Data\StaffReview;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Liberu\Ecommerce\ReviewsAndRatings\Support\ReadModels;

/**
 * The staff queue, filtered by derived display state and ordered by urgency.
 *
 * The state is a filter rather than a sort key because it is derived: ordering
 * "no decision first" across states means ordering on a nullable subquery, and
 * the three databases this fleet runs on do not agree about where nulls sort.
 * Defaulting to `Pending` gives the queue a moderator actually opens.
 */
final class ModerationQueue
{
    /** @return list<StaffReview> */
    public function __invoke(
        string $tenantId,
        ?DisplayState $state = DisplayState::Pending,
        int $limit = 50,
        int $offset = 0,
    ): array {
        $query = Expression::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('superseded_at');

        if ($state === DisplayState::Pending) {
            $query->whereDoesntHave('decisions');
        } elseif ($state !== null) {
            $query->whereHas(
                'latestDecision',
                static fn (Builder $decision): Builder => $decision->where('outcome', $state->value),
            );
        }

        return $query
            ->with(['facets', 'decisions', 'votes', 'flags', 'latestDecision', 'supersedes.decisions'])
            ->orderByDesc('screening_weight')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(ReadModels::staffReview(...))
            ->values()
            ->all();
    }
}
