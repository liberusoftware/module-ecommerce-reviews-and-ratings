<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Enums;

/**
 * Derived, never stored.
 *
 * An expression's display state is a fold over its moderation decisions. There
 * is no column anybody can flip, which is what makes "who decided this" always
 * answerable.
 */
enum DisplayState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withheld = 'withheld';
    case Escalated = 'escalated';

    /** Nothing is displayed by arriving (addendum §5.3). */
    public function isDisplayed(): bool
    {
        return $this === self::Approved;
    }
}
