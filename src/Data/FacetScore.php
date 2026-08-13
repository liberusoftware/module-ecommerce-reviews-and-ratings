<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Data;

use Liberu\Ecommerce\ReviewsAndRatings\Enums\FacetKind;

/** One breakdown score, as read. Always carries its own scale. */
final readonly class FacetScore
{
    public function __construct(
        public FacetKind $kind,
        public int $score,
        public int $scale,
    ) {}

    /** @return array{kind: string, score: int, scale: int} */
    public function toArray(): array
    {
        return ['kind' => $this->kind->value, 'score' => $this->score, 'scale' => $this->scale];
    }
}
