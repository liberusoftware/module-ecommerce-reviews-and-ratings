<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Events;

use Liberu\Ecommerce\ReviewsAndRatings\Data\VoteTally;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VoteDirection;

/** A vote was recorded or changed. The tally is the count after the write. */
final readonly class VoteCast
{
    public function __construct(
        public string $tenantId,
        public string $expressionReference,
        public VoteDirection $direction,
        public VoteTally $tally,
    ) {}
}
