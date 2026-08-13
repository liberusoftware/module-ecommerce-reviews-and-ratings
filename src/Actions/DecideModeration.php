<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ModerationRecord;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Events\ModerationDecided;
use Liberu\Ecommerce\ReviewsAndRatings\Support\Expressions;
use Liberu\Ecommerce\ReviewsAndRatings\Support\ReadModels;

/**
 * Append a decision. Never flip a flag.
 *
 * The expression's display state is derived from the latest decision, so an
 * approval, a retraction and a re-approval leave three rows naming three
 * actors, rather than one boolean that looks exactly like it was only ever
 * approved once. The reason is a closed enum: a free-text box beside a person's
 * name is where personal data gets typed.
 */
final readonly class DecideModeration
{
    public function __construct(private Dispatcher $events) {}

    public function __invoke(
        string $tenantId,
        string $reference,
        ModerationOutcome $outcome,
        ModerationReason $reason,
        string $actorReference,
        ?CarbonImmutable $occurredAt = null,
    ): ModerationRecord {
        $expression = Expressions::locate($tenantId, $reference);

        $decision = $expression->decisions()->create([
            'tenant_id' => $tenantId,
            'outcome' => $outcome,
            'reason' => $reason,
            'actor_reference' => $actorReference,
            'occurred_at' => $occurredAt ?? CarbonImmutable::now(),
        ]);

        $record = ReadModels::moderationRecord($decision, $expression->reference);

        $this->events->dispatch(new ModerationDecided($tenantId, $record));

        return $record;
    }
}
