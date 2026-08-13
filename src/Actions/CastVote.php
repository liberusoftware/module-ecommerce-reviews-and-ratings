<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Liberu\Ecommerce\ReviewsAndRatings\Data\VoteTally;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VoteDirection;
use Liberu\Ecommerce\ReviewsAndRatings\Events\VoteCast;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionRedacted;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\SelfVoteRefused;
use Liberu\Ecommerce\ReviewsAndRatings\Support\Expressions;

/**
 * One row per voter per expression, uniquely indexed.
 *
 * Voting twice is idempotent, not additive, and changing your mind updates your
 * row. The host increments an unlocked counter and records nobody, which makes
 * the sort order of a product page purchasable with curl in a loop.
 */
final readonly class CastVote
{
    public function __construct(private Dispatcher $events) {}

    public function __invoke(
        string $tenantId,
        string $reference,
        string $voterReference,
        VoteDirection $direction,
        ?CarbonImmutable $occurredAt = null,
    ): VoteTally {
        $expression = Expressions::locate($tenantId, $reference);

        if ($expression->isRedacted()) {
            throw ExpressionRedacted::withReference($reference);
        }

        // Refused, not ignored: a caller that cannot tell "counted" from
        // "quietly dropped" builds a control that lies about what it did.
        if ($expression->author_reference !== null && $expression->author_reference === $voterReference) {
            throw SelfVoteRefused::withReference($reference);
        }

        $expression->votes()->updateOrCreate(
            ['voter_reference' => $voterReference],
            [
                'tenant_id' => $tenantId,
                'direction' => $direction,
                'occurred_at' => $occurredAt ?? CarbonImmutable::now(),
            ],
        );

        $tally = $expression->load('votes')->tally();

        $this->events->dispatch(new VoteCast($tenantId, $reference, $direction, $tally));

        return $tally;
    }
}
