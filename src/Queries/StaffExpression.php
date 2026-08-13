<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Queries;

use Liberu\Ecommerce\ReviewsAndRatings\Data\StaffReview;
use Liberu\Ecommerce\ReviewsAndRatings\Support\Expressions;
use Liberu\Ecommerce\ReviewsAndRatings\Support\ReadModels;

/** One expression, in full, for somebody who is allowed to see all of it. */
final class StaffExpression
{
    public function __invoke(string $tenantId, string $reference): StaffReview
    {
        return ReadModels::staffReview(Expressions::locate($tenantId, $reference, [
            'facets', 'decisions', 'votes', 'flags', 'latestDecision', 'supersedes.decisions',
        ]));
    }
}
