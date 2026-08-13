<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\FlagReason;
use Liberu\Ecommerce\ReviewsAndRatings\Events\ExpressionFlagged;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionRedacted;
use Liberu\Ecommerce\ReviewsAndRatings\Support\Expressions;

/**
 * A reader reports an expression.
 *
 * Reporting hides nothing. It puts the expression in front of a person, and
 * only a person's decision can change what is displayed — otherwise the report
 * button is a heckler's veto with an API.
 */
final readonly class FlagExpression
{
    public function __construct(private Dispatcher $events) {}

    public function __invoke(
        string $tenantId,
        string $reference,
        string $reporterReference,
        FlagReason $reason,
        ?CarbonImmutable $occurredAt = null,
    ): int {
        $expression = Expressions::locate($tenantId, $reference);

        if ($expression->isRedacted()) {
            throw ExpressionRedacted::withReference($reference);
        }

        $expression->flags()->updateOrCreate(
            ['reporter_reference' => $reporterReference],
            [
                'tenant_id' => $tenantId,
                'reason' => $reason,
                'occurred_at' => $occurredAt ?? CarbonImmutable::now(),
            ],
        );

        $count = $expression->flags()->count();

        $this->events->dispatch(new ExpressionFlagged($tenantId, $reference, $reason, $count));

        return $count;
    }
}
