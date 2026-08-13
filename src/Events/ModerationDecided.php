<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Events;

use Liberu\Ecommerce\ReviewsAndRatings\Data\ModerationRecord;

/**
 * A decision was appended. This is the only way an expression's display state
 * can ever change, so a cache of published aggregates listens here.
 */
final readonly class ModerationDecided
{
    public function __construct(
        public string $tenantId,
        public ModerationRecord $record,
    ) {}
}
