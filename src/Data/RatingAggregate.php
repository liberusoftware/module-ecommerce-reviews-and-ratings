<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Data;

use Liberu\Ecommerce\ReviewsAndRatings\Enums\AggregatePopulation;

/**
 * A numerator, a denominator, a scale, and the population they summarise.
 *
 * Never a rounded float: the consumer rounds. A `4.3` from `4.2999` and a `4.3`
 * from `4.3` are different facts, and a rounded average cannot be re-aggregated
 * across pages or stores without drifting.
 */
final readonly class RatingAggregate
{
    public function __construct(
        public int $sum,
        public int $count,
        public int $scale,
        public AggregatePopulation $population,
    ) {}

    /** @return array{sum: int, count: int, scale: int, population: string} */
    public function toArray(): array
    {
        return [
            'sum' => $this->sum,
            'count' => $this->count,
            'scale' => $this->scale,
            'population' => $this->population->value,
        ];
    }
}
