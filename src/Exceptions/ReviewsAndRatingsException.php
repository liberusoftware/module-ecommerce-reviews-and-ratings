<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Exceptions;

use RuntimeException;

/**
 * Every refusal this module makes, carrying the HTTP status an adapter should
 * map it to.
 *
 * The property is `$errorCode`, not `$code`: a readonly promoted `$code` on an
 * Exception subclass is a fatal at class load, not a test failure.
 */
abstract class ReviewsAndRatingsException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    /** The status an HTTP adapter must answer with. */
    abstract public function status(): int;
}
