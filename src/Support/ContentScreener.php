<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Support;

use Liberu\Ecommerce\ReviewsAndRatings\Contracts\ScreensContent;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ScreeningOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ScreeningUnavailable;
use Throwable;

/**
 * The other seam, and it fails in the other direction.
 *
 * An expression carrying no free text has nothing to screen, so a star-only
 * rating is accepted on a deployment with no screener bound. An expression
 * carrying a body is refused with 503, because accepting free text with no
 * screening and no moderator notification publishes unscreened speech on a
 * merchant's page.
 */
final readonly class ContentScreener
{
    public function __construct(private ?ScreensContent $screener = null) {}

    public function screen(?string $body, string $locale): ScreeningOutcome
    {
        if ($body === null) {
            return ScreeningOutcome::clean();
        }

        if ($this->screener === null) {
            throw ScreeningUnavailable::unbound();
        }

        try {
            return $this->screener->screen($body, $locale);
        } catch (ScreeningUnavailable $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ScreeningUnavailable::failed($exception->getMessage());
        }
    }
}
