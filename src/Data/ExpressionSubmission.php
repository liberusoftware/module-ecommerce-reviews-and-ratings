<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Data;

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionSource;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\IncentiveDisclosure;

/**
 * A shopper's opinion about a product, as submitted.
 *
 * `$productReference` and `$authorReference` are opaque. This module never
 * dereferences either, joins to neither, and holds no other fact about the
 * person than the display name they chose for this one expression.
 *
 * `$score` and `$body` are both optional and at least one must be present: a
 * star with no words is normal, and so is a written review with no star. They
 * are one record either way.
 */
final readonly class ExpressionSubmission
{
    /** @param  list<FacetSubmission>  $facets */
    public function __construct(
        public string $tenantId,
        public string $productReference,
        public string $authorReference,
        public string $authorDisplayName,
        public ?int $score = null,
        public ?int $scale = null,
        public ?string $body = null,
        public string $locale = 'en',
        public ExpressionSource $source = ExpressionSource::FirstParty,
        public ?string $sourceReference = null,
        public IncentiveDisclosure $incentive = IncentiveDisclosure::None,
        public array $facets = [],
        public ?CarbonImmutable $occurredAt = null,
    ) {}
}
