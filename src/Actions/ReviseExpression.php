<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionReceipt;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionRevision;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionKind;
use Liberu\Ecommerce\ReviewsAndRatings\Events\ExpressionRevised;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\DuplicateExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionAlreadySuperseded;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionRedacted;
use Liberu\Ecommerce\ReviewsAndRatings\Support\ContentScreener;
use Liberu\Ecommerce\ReviewsAndRatings\Support\Expressions;
use Liberu\Ecommerce\ReviewsAndRatings\Support\ExpressionWriter;
use Liberu\Ecommerce\ReviewsAndRatings\Support\ReadModels;
use Liberu\Ecommerce\ReviewsAndRatings\Support\SubmissionRules;

/**
 * An edit is a new expression that supersedes the old one.
 *
 * No `UPDATE` touches what was said. The old row stays, still says what it
 * said, still carries the decisions made about it — which is the only way a
 * moderator can ever be told "they edited this after you approved it".
 *
 * The new expression starts at `pending` again, however the old one was
 * decided. Verification is inherited rather than re-asked: the purchase seam
 * answers a question about an author and a product, and neither changed.
 *
 * Works on a merchant reply too — a merchant editing a reply supersedes it.
 */
final readonly class ReviseExpression
{
    public function __construct(
        private Dispatcher $events,
        private ContentScreener $screener,
    ) {}

    public function __invoke(string $tenantId, string $reference, ExpressionRevision $revision): ExpressionReceipt
    {
        $original = Expressions::locate($tenantId, $reference, ['decisions', 'latestDecision']);

        if ($original->isRedacted()) {
            throw ExpressionRedacted::withReference($reference);
        }

        if ($original->superseded_at !== null) {
            throw ExpressionAlreadySuperseded::withReference($reference);
        }

        $body = $revision->body === null ? null : trim($revision->body);

        if ($original->kind === ExpressionKind::MerchantReply) {
            SubmissionRules::assertBody((string) $body);
        } else {
            SubmissionRules::assertContent($revision->score, $revision->scale, $body, $revision->facets);
        }

        $screening = $this->screener->screen($body, $original->locale);
        $occurredAt = $revision->occurredAt ?? CarbonImmutable::now();
        $wasDisplayed = $original->isDisplayed();

        // Retire the old row first. Its live key is what the unique index holds,
        // and the successor cannot claim the slot until it is released.
        $original->superseded_at = $occurredAt;
        $original->save();

        $expression = ExpressionWriter::write([
            'tenant_id' => $original->tenant_id,
            'kind' => $original->kind,
            'product_reference' => $original->product_reference,
            'parent_expression_id' => $original->parent_expression_id,
            'supersedes_id' => $original->id,
            'author_reference' => $original->author_reference,
            'author_display_name' => $original->author_display_name,
            'score' => $revision->score,
            'scale' => $revision->scale,
            'body' => $body,
            'locale' => $original->locale,
            'source' => $original->source,
            'source_reference' => $original->source_reference,
            'incentive' => $revision->incentive ?? $original->incentive,
            'verification' => $original->verification,
            'verified_at' => $original->verified_at,
            'occurred_at' => $occurredAt,
        ], $revision->facets, $screening, DuplicateExpression::onProduct((string) $original->product_reference));

        $this->events->dispatch(new ExpressionRevised(
            $expression->tenant_id,
            $expression->reference,
            $original->reference,
            $wasDisplayed,
        ));

        return ReadModels::receipt($expression->setRelation('supersedes', $original));
    }
}
