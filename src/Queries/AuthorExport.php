<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Queries;

use Liberu\Ecommerce\ReviewsAndRatings\Data\AuthorExportRecord;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Liberu\Ecommerce\ReviewsAndRatings\Support\ReadModels;

/**
 * Everything this module holds about one author's own expressions, including
 * every superseded revision and the moderation state and reasons.
 *
 * A review held pending is data held about a person that no page shows them, so
 * a subject access request that omits it is incomplete.
 */
final class AuthorExport
{
    /** @return list<AuthorExportRecord> */
    public function __invoke(string $tenantId, string $authorReference): array
    {
        return Expression::query()
            ->with(['facets', 'decisions', 'latestDecision'])
            ->where('tenant_id', $tenantId)
            ->where('author_reference', $authorReference)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(ReadModels::authorExportRecord(...))
            ->values()
            ->all();
    }
}
