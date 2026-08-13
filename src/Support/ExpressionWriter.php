<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Liberu\Ecommerce\ReviewsAndRatings\Data\FacetSubmission;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ScreeningOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ScreeningDisposition;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\DuplicateExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;

/** The one write path onto `reviews_expressions`, so the rules are in one place. */
final class ExpressionWriter
{
    /** The actor an escalation is attributed to. Not a person, and it says so. */
    public const string SCREENING_ACTOR = 'system:screening';

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<FacetSubmission>  $facets
     */
    public static function write(array $attributes, array $facets, ScreeningOutcome $screening, DuplicateExpression $onDuplicate): Expression
    {
        return DB::transaction(static function () use ($attributes, $facets, $screening, $onDuplicate): Expression {
            try {
                $expression = Expression::query()->create($attributes + [
                    'screening_priority' => $screening->priority,
                    'screening_signals' => $screening->signalValues(),
                ]);
            } catch (QueryException $exception) {
                // The unique index on live_key is what makes this a real refusal
                // rather than the advisory 409 a check-then-act read produces:
                // of two concurrent writers, exactly one gets here.
                if (str_contains($exception->getMessage(), 'live_key')) {
                    throw $onDuplicate;
                }

                throw $exception;
            }

            foreach ($facets as $facet) {
                $expression->facets()->create([
                    'tenant_id' => $expression->tenant_id,
                    'facet' => $facet->kind,
                    'score' => $facet->score,
                    'scale' => $facet->scale,
                ]);
            }

            if ($screening->disposition === ScreeningDisposition::Escalate) {
                // A machine does not moderate speech here. Escalating is still a
                // queue position, and it is recorded as a decision so that the
                // display state has exactly one derivation.
                $expression->decisions()->create([
                    'tenant_id' => $expression->tenant_id,
                    'outcome' => ModerationOutcome::Escalated,
                    'reason' => ModerationReason::MachineEscalation,
                    'actor_reference' => self::SCREENING_ACTOR,
                    'occurred_at' => $expression->occurred_at,
                ]);
            }

            return $expression->load(['facets', 'decisions', 'latestDecision', 'votes', 'flags', 'replies']);
        });
    }
}
