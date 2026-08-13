<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Enums;

/** How far up the moderation queue screening put an expression. */
enum ScreeningPriority: string
{
    case Routine = 'routine';
    case Elevated = 'elevated';
    case Urgent = 'urgent';

    /** Descending sort weight for the queue: urgent first. */
    public function weight(): int
    {
        return match ($this) {
            self::Urgent => 3,
            self::Elevated => 2,
            self::Routine => 1,
        };
    }
}
