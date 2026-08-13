<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Enums;

/**
 * What automated screening asks the module to do with an expression.
 *
 * There is no `Reject`. A machine does not moderate speech in this module; it
 * queues it for a person, with more or less urgency (addendum §6.2).
 */
enum ScreeningDisposition: string
{
    case Queue = 'queue';
    case Escalate = 'escalate';
}
