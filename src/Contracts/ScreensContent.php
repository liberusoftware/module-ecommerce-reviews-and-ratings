<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Contracts;

use Liberu\Ecommerce\ReviewsAndRatings\Data\ScreeningOutcome;

/**
 * The seam to automated screening: profanity, spam, personal data in the body.
 *
 * Unlike ConfirmsPurchase, leaving this unbound is a configuration error and
 * not a deployment. An unbound optional seam is safe when its absence removes a
 * *claim* and unsafe when its absence removes a *control*, and this one is a
 * control: a module that accepts free text with no screening and no moderator
 * notification is publishing unscreened speech on a merchant's page. So a write
 * carrying a body refuses with 503 rather than proceeding.
 *
 * An implementation never rejects. It says how urgently a person should look.
 */
interface ScreensContent
{
    public function screen(string $body, string $locale): ScreeningOutcome;
}
