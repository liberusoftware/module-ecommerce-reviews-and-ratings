<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Enums;

/** A vote is a record carrying a direction, never an increment. */
enum VoteDirection: string
{
    case Helpful = 'helpful';
    case Unhelpful = 'unhelpful';
}
