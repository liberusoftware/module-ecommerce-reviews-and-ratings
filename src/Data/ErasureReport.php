<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Data;

/** What an erasure touched. Counts, never content. */
final readonly class ErasureReport
{
    public function __construct(
        public int $expressionsRedacted,
        public int $votesRedacted,
        public int $flagsRedacted,
    ) {}
}
