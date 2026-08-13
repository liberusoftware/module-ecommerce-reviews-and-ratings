<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Support;

use Illuminate\Contracts\Events\Dispatcher;
use Liberu\Ecommerce\ReviewsAndRatings\Contracts\ConfirmsPurchase;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionSource;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VerificationState;
use Liberu\Ecommerce\ReviewsAndRatings\Events\PurchaseVerificationDegraded;
use Throwable;

/**
 * The one place `unknown` is produced, so it is the one place it could be
 * flattened — and it is not.
 *
 * Three different situations all mean "the module could not ask", and none of
 * them means "they did not buy it": the seam is unbound, the seam threw, or the
 * expression came from another platform whose order book this module cannot
 * read. Only a bound verifier answering `purchased: false` yields `unverified`.
 *
 * The `= null` default on the constructor is load-bearing. The container falls
 * back to a default only when the parameter has one; without it an unbound seam
 * throws BindingResolutionException instead of degrading.
 */
final readonly class PurchaseVerifier
{
    public function __construct(
        private Dispatcher $events,
        private ?ConfirmsPurchase $verifier = null,
    ) {}

    public function verify(string $tenantId, ExpressionSource $source, string $authorReference, string $productReference): VerificationResult
    {
        if (! $source->isVerifiable() || $this->verifier === null) {
            return new VerificationResult(VerificationState::Unknown);
        }

        try {
            $confirmation = $this->verifier->confirm($authorReference, $productReference);
        } catch (Throwable $exception) {
            // Never blocks the write. Refusing speech because a service is down
            // is worse than publishing it unbadged.
            $this->events->dispatch(new PurchaseVerificationDegraded($tenantId, $productReference, $exception->getMessage()));

            return new VerificationResult(VerificationState::Unknown);
        }

        if ($confirmation === null) {
            return new VerificationResult(VerificationState::Unknown);
        }

        return $confirmation->purchased
            ? new VerificationResult(VerificationState::Verified, $confirmation->confirmedAt)
            : new VerificationResult(VerificationState::Unverified);
    }
}
