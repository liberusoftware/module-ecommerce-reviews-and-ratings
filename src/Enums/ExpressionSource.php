<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Enums;

/** Where an expression came from. Syndicated and imported history says so. */
enum ExpressionSource: string
{
    case FirstParty = 'first_party';
    case Syndicated = 'syndicated';
    case Imported = 'imported';

    /**
     * Only first-party speech can be checked against this merchant's own order
     * book. This module cannot read another platform's, so a syndicated or
     * imported expression is permanently `unknown` (addendum §5.11).
     */
    public function isVerifiable(): bool
    {
        return $this === self::FirstParty;
    }
}
