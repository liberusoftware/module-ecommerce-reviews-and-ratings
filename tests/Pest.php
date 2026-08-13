<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\DecideModeration;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\RecordExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\RecordMerchantReply;
use Liberu\Ecommerce\ReviewsAndRatings\Actions\ReviseExpression;
use Liberu\Ecommerce\ReviewsAndRatings\Contracts\ConfirmsPurchase;
use Liberu\Ecommerce\ReviewsAndRatings\Contracts\ScreensContent;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionReceipt;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionRevision;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ExpressionSubmission;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ModerationRecord;
use Liberu\Ecommerce\ReviewsAndRatings\Data\ReplySubmission;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Tests\Support\RecordingScreener;
use Liberu\Ecommerce\ReviewsAndRatings\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Every reference in this suite is a string no other module has heard of. The
 * module never dereferences one, so nothing here needs a catalogue, an order
 * book or an identity store to exist.
 */
const TENANT = 'tenant-alpha';
const PRODUCT = 'catalogue:sku-77193-not-a-real-product';
const AUTHOR = 'shopper:8c1f-nobody-has-this-id';

function bindScreener(?ScreensContent $screener = null): ScreensContent
{
    $screener ??= new RecordingScreener();

    app()->instance(ScreensContent::class, $screener);

    return $screener;
}

function bindVerifier(ConfirmsPurchase $verifier): ConfirmsPurchase
{
    app()->instance(ConfirmsPurchase::class, $verifier);

    return $verifier;
}

function submit(ExpressionSubmission $submission): ExpressionReceipt
{
    return app(RecordExpression::class)($submission);
}

function submission(array $overrides = []): ExpressionSubmission
{
    return new ExpressionSubmission(...array_merge([
        'tenantId' => TENANT,
        'productReference' => PRODUCT,
        'authorReference' => AUTHOR,
        'authorDisplayName' => 'A. Shopper',
        'score' => 4,
        'scale' => 5,
        'occurredAt' => CarbonImmutable::parse('2026-01-01 09:00:00'),
    ], $overrides));
}

function revise(string $reference, ExpressionRevision $revision): ExpressionReceipt
{
    return app(ReviseExpression::class)(TENANT, $reference, $revision);
}

function reply(string $parentReference, string $body = 'Sorry to hear that — we have sent a replacement.'): ExpressionReceipt
{
    return app(RecordMerchantReply::class)(new ReplySubmission(
        tenantId: TENANT,
        parentReference: $parentReference,
        authorReference: 'merchant:support-desk',
        authorDisplayName: 'Acme Support',
        body: $body,
    ));
}

function approve(string $reference, string $actor = 'staff:mo'): ModerationRecord
{
    return app(DecideModeration::class)(
        TENANT,
        $reference,
        ModerationOutcome::Approved,
        ModerationReason::Compliant,
        $actor,
    );
}

function decide(string $reference, ModerationOutcome $outcome, ModerationReason $reason, string $actor = 'staff:mo'): ModerationRecord
{
    return app(DecideModeration::class)(TENANT, $reference, $outcome, $reason, $actor);
}
