<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Data;

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionSource;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\IncentiveDisclosure;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VerificationState;

/**
 * The public projection: everything a stranger may see, and nothing else.
 *
 * This is a separate schema from the staff projection rather than a blanked
 * copy of it. It has no author reference, no tenant, no moderation history and
 * no reason — not hidden, absent — so the host's live fault, where one `->with()`
 * serialised every reviewer's postal address on an unauthenticated route, is
 * structurally impossible here rather than merely remembered.
 *
 * `$authorDisplayName` is the name the author chose for this one expression,
 * denormalised at write time. It is never resolved from an identity store.
 */
final readonly class PublicReview
{
    /** @param  list<FacetScore>  $facets */
    public function __construct(
        public string $reference,
        public ?int $score,
        public ?int $scale,
        public ?string $body,
        public string $authorDisplayName,
        public VerificationState $verification,
        public ExpressionSource $source,
        public IncentiveDisclosure $incentive,
        public VoteTally $votes,
        public array $facets,
        public ?PublicReply $reply,
        public bool $edited,
        public CarbonImmutable $occurredAt,
        public CarbonImmutable $recordedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'score' => $this->score,
            'scale' => $this->scale,
            'body' => $this->body,
            'author_display_name' => $this->authorDisplayName,
            'verification' => $this->verification->value,
            'source' => $this->source->value,
            'incentive' => $this->incentive->value,
            'votes' => $this->votes->toArray(),
            'facets' => array_map(static fn (FacetScore $facet): array => $facet->toArray(), $this->facets),
            'reply' => $this->reply?->toArray(),
            'edited' => $this->edited,
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'recorded_at' => $this->recordedAt->toIso8601String(),
        ];
    }
}
