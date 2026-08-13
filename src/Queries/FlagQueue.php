<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Queries;

use Liberu\Ecommerce\ReviewsAndRatings\Data\FlagRecord;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Flag;

/** What readers have reported, newest first. Staff only. */
final class FlagQueue
{
    /** @return list<FlagRecord> */
    public function __invoke(string $tenantId, int $limit = 50, int $offset = 0): array
    {
        return Flag::query()
            ->with('expression')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(static fn (Flag $flag): FlagRecord => new FlagRecord(
                expressionReference: $flag->expression->reference,
                reason: $flag->reason,
                reporterReference: $flag->reporter_reference,
                occurredAt: $flag->occurred_at,
            ))
            ->values()
            ->all();
    }
}
