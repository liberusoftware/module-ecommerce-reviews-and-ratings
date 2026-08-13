<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Data;

use Carbon\CarbonImmutable;

/** A merchant reply, as a stranger may see it. No author reference. */
final readonly class PublicReply
{
    public function __construct(
        public string $reference,
        public string $body,
        public string $authorDisplayName,
        public CarbonImmutable $occurredAt,
    ) {}

    /** @return array{reference: string, body: string, author_display_name: string, occurred_at: string} */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'body' => $this->body,
            'author_display_name' => $this->authorDisplayName,
            'occurred_at' => $this->occurredAt->toIso8601String(),
        ];
    }
}
