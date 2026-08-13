<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Support;

use Liberu\Ecommerce\ReviewsAndRatings\Data\AuthorExportRecord;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionReceipt;
use Liberu\Ecommerce\ReviewsAndRatings\Data\FacetScore;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ModerationRecord;
use Liberu\Ecommerce\ReviewsAndRatings\Data\PublicReply;
use Liberu\Ecommerce\ReviewsAndRatings\Data\PublicReview;
use Liberu\Ecommerce\ReviewsAndRatings\Data\StaffReview;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Models\Expression;
use Liberu\Ecommerce\ReviewsAndRatings\Models\ExpressionFacet;
use Liberu\Ecommerce\ReviewsAndRatings\Models\ModerationDecision;

/**
 * Where rows become read models.
 *
 * The public projection and the staff projection are built by two separate
 * functions with two separate field lists, not by one function with a `$staff`
 * flag. That is the whole point of §5.9: there is no code path where forgetting
 * to blank a field publishes an author reference, because the public builder
 * never reads one.
 */
final class ReadModels
{
    public static function publicReview(Expression $expression): PublicReview
    {
        $reply = $expression->liveReply();

        return new PublicReview(
            reference: $expression->reference,
            score: $expression->score,
            scale: $expression->scale,
            body: $expression->body,
            authorDisplayName: (string) $expression->author_display_name,
            verification: $expression->verification,
            source: $expression->source,
            incentive: $expression->incentive,
            votes: $expression->tally(),
            facets: self::facets($expression),
            reply: $reply === null ? null : new PublicReply(
                reference: $reply->reference,
                body: (string) $reply->body,
                authorDisplayName: (string) $reply->author_display_name,
                occurredAt: $reply->occurred_at,
            ),
            edited: $expression->supersedes_id !== null,
            occurredAt: $expression->occurred_at,
            recordedAt: $expression->created_at,
        );
    }

    public static function receipt(Expression $expression): ExpressionReceipt
    {
        return new ExpressionReceipt(
            reference: $expression->reference,
            displayState: $expression->displayState(),
            verification: $expression->verification,
            screeningPriority: $expression->screening_priority,
            supersedesReference: $expression->supersedes?->reference,
            occurredAt: $expression->occurred_at,
            recordedAt: $expression->created_at,
        );
    }

    public static function staffReview(Expression $expression): StaffReview
    {
        $supersedes = $expression->supersedes;

        return new StaffReview(
            reference: $expression->reference,
            tenantId: $expression->tenant_id,
            kind: $expression->kind,
            productReference: $expression->product_reference,
            parentReference: $expression->parent_expression_id === null ? null : self::parentReference($expression),
            authorReference: $expression->author_reference,
            authorDisplayName: $expression->author_display_name,
            score: $expression->score,
            scale: $expression->scale,
            body: $expression->body,
            locale: $expression->locale,
            source: $expression->source,
            sourceReference: $expression->source_reference,
            incentive: $expression->incentive,
            verification: $expression->verification,
            displayState: $expression->displayState(),
            screeningPriority: $expression->screening_priority,
            screeningSignals: $expression->screeningSignals(),
            facets: self::facets($expression),
            votes: $expression->tally(),
            flagCount: $expression->flags->count(),
            moderationHistory: self::moderationHistory($expression),
            supersedesReference: $supersedes?->reference,
            // The single most important thing a moderator can be told: they
            // approved something, and then it changed.
            editedAfterApproval: $supersedes !== null && $supersedes->decisions
                ->contains(static fn (ModerationDecision $decision): bool => $decision->outcome === ModerationOutcome::Approved),
            isSuperseded: $expression->superseded_at !== null,
            isRedacted: $expression->isRedacted(),
            occurredAt: $expression->occurred_at,
            recordedAt: $expression->created_at,
        );
    }

    public static function authorExportRecord(Expression $expression): AuthorExportRecord
    {
        return new AuthorExportRecord(
            reference: $expression->reference,
            productReference: $expression->product_reference,
            score: $expression->score,
            scale: $expression->scale,
            body: $expression->body,
            authorDisplayName: $expression->author_display_name,
            locale: $expression->locale,
            source: $expression->source,
            incentive: $expression->incentive,
            verification: $expression->verification,
            displayState: $expression->displayState(),
            facets: self::facets($expression),
            moderationHistory: self::moderationHistory($expression),
            isSuperseded: $expression->superseded_at !== null,
            isRedacted: $expression->isRedacted(),
            occurredAt: $expression->occurred_at,
            recordedAt: $expression->created_at,
        );
    }

    public static function moderationRecord(ModerationDecision $decision, string $expressionReference): ModerationRecord
    {
        return new ModerationRecord(
            expressionReference: $expressionReference,
            outcome: $decision->outcome,
            reason: $decision->reason,
            actorReference: $decision->actor_reference,
            occurredAt: $decision->occurred_at,
            recordedAt: $decision->created_at,
        );
    }

    /** @return list<FacetScore> */
    private static function facets(Expression $expression): array
    {
        return $expression->facets
            ->map(static fn (ExpressionFacet $facet): FacetScore => new FacetScore($facet->facet, $facet->score, $facet->scale))
            ->values()
            ->all();
    }

    /** @return list<ModerationRecord> */
    private static function moderationHistory(Expression $expression): array
    {
        return $expression->decisions
            ->map(static fn (ModerationDecision $decision): ModerationRecord => self::moderationRecord($decision, $expression->reference))
            ->values()
            ->all();
    }

    private static function parentReference(Expression $expression): ?string
    {
        return Expression::query()->whereKey($expression->parent_expression_id)->value('reference');
    }
}
