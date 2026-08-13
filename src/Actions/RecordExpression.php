<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionReceipt;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionSubmission;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionKind;
use Liberu\Ecommerce\ReviewsAndRatings\Events\ExpressionRecorded;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\DuplicateExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\InvalidExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Support\ContentScreener;
use Liberu\Ecommerce\ReviewsAndRatings\Support\ExpressionWriter;
use Liberu\Ecommerce\ReviewsAndRatings\Support\PurchaseVerifier;
use Liberu\Ecommerce\ReviewsAndRatings\Support\ReadModels;
use Liberu\Ecommerce\ReviewsAndRatings\Support\SubmissionRules;

/**
 * Record that somebody expressed an opinion about a product.
 *
 * It is recorded, not displayed: nothing arrives approved, ratings included,
 * which is what closes the host's bypass where a one-star score lands in the
 * public average while the sentence beside it waits for moderation.
 */
final readonly class RecordExpression
{
    public function __construct(
        private Dispatcher $events,
        private ContentScreener $screener,
        private PurchaseVerifier $verifier,
    ) {}

    public function __invoke(ExpressionSubmission $submission): ExpressionReceipt
    {
        SubmissionRules::assertDisplayName($submission->authorDisplayName);

        $body = $submission->body === null ? null : trim($submission->body);

        SubmissionRules::assertContent($submission->score, $submission->scale, $body, $submission->facets);

        if (! $submission->source->isVerifiable() && ($submission->sourceReference === null || trim($submission->sourceReference) === '')) {
            throw InvalidExpression::because('An expression brought in from another platform must carry the reference it had there.');
        }

        $screening = $this->screener->screen($body, $submission->locale);
        $verification = $this->verifier->verify(
            $submission->tenantId,
            $submission->source,
            $submission->authorReference,
            $submission->productReference,
        );

        $occurredAt = $submission->occurredAt ?? CarbonImmutable::now();

        $expression = ExpressionWriter::write([
            'tenant_id' => $submission->tenantId,
            'kind' => ExpressionKind::ShopperReview,
            'product_reference' => $submission->productReference,
            'author_reference' => $submission->authorReference,
            'author_display_name' => trim($submission->authorDisplayName),
            'score' => $submission->score,
            'scale' => $submission->scale,
            'body' => $body,
            'locale' => $submission->locale,
            'source' => $submission->source,
            'source_reference' => $submission->sourceReference,
            'incentive' => $submission->incentive,
            'verification' => $verification->state,
            'verified_at' => $verification->confirmedAt,
            'occurred_at' => $occurredAt,
        ], $submission->facets, $screening, DuplicateExpression::onProduct($submission->productReference));

        $this->events->dispatch(new ExpressionRecorded(
            $expression->tenant_id,
            $expression->reference,
            $expression->kind,
            $expression->product_reference,
            $expression->verification,
        ));

        return ReadModels::receipt($expression);
    }
}
