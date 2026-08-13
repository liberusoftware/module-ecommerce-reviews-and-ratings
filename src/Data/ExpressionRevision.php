<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Data;

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\IncentiveDisclosure;

/**
 * What an edit replaces.
 *
 * The product, the author and the display name are carried over from the
 * expression being superseded: an edit is the same person still talking about
 * the same thing. Everything a revision does not restate is dropped, because a
 * revision is a whole new expression, not a patch.
 */
final readonly class ExpressionRevision
{
    /** @param  list<FacetSubmission>  $facets */
    public function __construct(
        public ?int $score = null,
        public ?int $scale = null,
        public ?string $body = null,
        public ?IncentiveDisclosure $incentive = null,
        public array $facets = [],
        public ?CarbonImmutable $occurredAt = null,
    ) {}
}
