<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Data;

/** Vote totals, derived by counting rows. There is no counter column. */
final readonly class VoteTally
{
    public function __construct(
        public int $helpful = 0,
        public int $unhelpful = 0,
    ) {}

    /** @return array{helpful: int, unhelpful: int} */
    public function toArray(): array
    {
        return ['helpful' => $this->helpful, 'unhelpful' => $this->unhelpful];
    }
}
