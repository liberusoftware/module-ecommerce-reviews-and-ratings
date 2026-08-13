<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionReceipt;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ReplySubmission;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionKind;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionSource;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VerificationState;
use Liberu\Ecommerce\ReviewsAndRatings\Events\MerchantReplied;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\DuplicateExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionAlreadySuperseded;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionRedacted;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\InvalidExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Support\ContentScreener;
use Liberu\Ecommerce\ReviewsAndRatings\Support\Expressions;
use Liberu\Ecommerce\ReviewsAndRatings\Support\ExpressionWriter;
use Liberu\Ecommerce\ReviewsAndRatings\Support\ReadModels;
use Liberu\Ecommerce\ReviewsAndRatings\Support\SubmissionRules;

/**
 * A merchant answers one expression.
 *
 * A reply is an expression by a different kind of author: same append-only
 * rules, same screening, same moderation, and it is pending until somebody
 * decides otherwise. It is never anonymous and never inherits the shopper's
 * author reference, and it hangs off the parent expression rather than off the
 * product — a reply is an answer to a person, not a second opinion.
 */
final readonly class RecordMerchantReply
{
    public function __construct(
        private Dispatcher $events,
        private ContentScreener $screener,
    ) {}

    public function __invoke(ReplySubmission $submission): ExpressionReceipt
    {
        SubmissionRules::assertDisplayName($submission->authorDisplayName);

        $body = trim($submission->body);

        SubmissionRules::assertBody($body);

        $parent = Expressions::locate($submission->tenantId, $submission->parentReference);

        if ($parent->kind !== ExpressionKind::ShopperReview) {
            throw InvalidExpression::because('A merchant reply answers a shopper review, not another reply.');
        }

        if ($parent->isRedacted()) {
            throw ExpressionRedacted::withReference($submission->parentReference);
        }

        if ($parent->superseded_at !== null) {
            throw ExpressionAlreadySuperseded::withReference($submission->parentReference);
        }

        $screening = $this->screener->screen($body, $submission->locale);
        $occurredAt = $submission->occurredAt ?? CarbonImmutable::now();

        $expression = ExpressionWriter::write([
            'tenant_id' => $submission->tenantId,
            'kind' => ExpressionKind::MerchantReply,
            'parent_expression_id' => $parent->id,
            'author_reference' => $submission->authorReference,
            'author_display_name' => trim($submission->authorDisplayName),
            'body' => $body,
            'locale' => $submission->locale,
            'source' => ExpressionSource::FirstParty,
            'verification' => VerificationState::Unknown,
            'occurred_at' => $occurredAt,
        ], [], $screening, DuplicateExpression::onReply($submission->parentReference));

        $this->events->dispatch(new MerchantReplied($expression->tenant_id, $expression->reference, $parent->reference));

        return ReadModels::receipt($expression);
    }
}
