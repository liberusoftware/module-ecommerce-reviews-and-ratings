<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Exceptions;

/**
 * 503. Automated screening is not bound, or it failed.
 *
 * Unlike the purchase seam, an unbound screener is a configuration error rather
 * than a deployment: its absence removes a control, not a claim. The safe
 * direction to fail in is "not accepting reviews right now".
 */
final class ScreeningUnavailable extends ReviewsAndRatingsException
{
    public static function unbound(): self
    {
        return new self('screening_unavailable', 'No ScreensContent implementation is bound; this module will not accept free text unscreened.');
    }

    public static function failed(string $reason): self
    {
        return new self('screening_failed', "Content screening failed: {$reason}");
    }

    public function status(): int
    {
        return 503;
    }
}
