<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings;

use Illuminate\Support\ServiceProvider;

/**
 * Loads this module's migrations, and binds nothing.
 *
 * The package ships no `extra.laravel.providers`, so Composer installing it
 * boots nothing at all — the host's module manager registers this provider only
 * when the module is named in `MODULES_ENABLED`. That is what "installed" and
 * "enabled" being different things looks like.
 *
 * Neither seam is bound here. `ConfirmsPurchase` unbound is a valid deployment
 * that yields `unknown`; `ScreensContent` unbound refuses writes carrying free
 * text with a 503. Binding either is the composing application's decision, and
 * a default implementation shipped from here would make one of those two
 * outcomes silently impossible to reach.
 */
class ReviewsAndRatingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
