<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Enums;

/**
 * The filter that produced an aggregate.
 *
 * An aggregate that does not name its population is not a fact. `Displayed` is
 * the only one a shopper may see; `Recorded` is a staff figure and is named
 * differently on purpose (addendum §5.4).
 */
enum AggregatePopulation: string
{
    case Displayed = 'displayed';
    case Recorded = 'recorded';
}
