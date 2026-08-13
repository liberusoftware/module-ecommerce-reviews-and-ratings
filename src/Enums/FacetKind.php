<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Enums;

/**
 * A breakdown score on an expression.
 *
 * Facets are rows, never nullable columns on the parent: nullable columns plus
 * arithmetic is exactly how the host turns a five-star rating into 1.25 stars.
 */
enum FacetKind: string
{
    case Quality = 'quality';
    case Value = 'value';
    case Price = 'price';
    case Accuracy = 'accuracy';
    case Delivery = 'delivery';
}
