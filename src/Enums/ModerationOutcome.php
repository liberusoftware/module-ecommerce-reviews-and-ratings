<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Enums;

/** The merchant's answer to "should this be shown". */
enum ModerationOutcome: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withheld = 'withheld';
    case Escalated = 'escalated';

    public function displayState(): DisplayState
    {
        return match ($this) {
            self::Approved => DisplayState::Approved,
            self::Rejected => DisplayState::Rejected,
            self::Withheld => DisplayState::Withheld,
            self::Escalated => DisplayState::Escalated,
        };
    }
}
