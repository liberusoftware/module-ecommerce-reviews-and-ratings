<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Events;

use Liberu\Ecommerce\ReviewsAndRatings\Data\ErasureReport;

/** An author's content is gone and the shape of the record is intact. */
final readonly class AuthorErased
{
    public function __construct(
        public string $tenantId,
        public ErasureReport $report,
    ) {}
}
