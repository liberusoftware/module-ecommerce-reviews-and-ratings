<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Data;

use Liberu\Ecommerce\ReviewsAndRatings\Enums\FacetKind;

/** One breakdown score on a submitted expression. */
final readonly class FacetSubmission
{
    public function __construct(
        public FacetKind $kind,
        public int $score,
        public int $scale,
    ) {}
}
