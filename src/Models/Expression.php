<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Liberu\Ecommerce\ReviewsAndRatings\Data\VoteTally;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\DisplayState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionKind;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ExpressionSource;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\IncentiveDisclosure;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ScreeningPriority;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VerificationState;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VoteDirection;
use Liberu\Ecommerce\ReviewsAndRatings\Exceptions\ExpressionIsImmutable;

/**
 * A historical fact: somebody said something about something, at a moment.
 *
 * @property int $id
 * @property string $reference
 * @property string $tenant_id
 * @property ExpressionKind $kind
 * @property string|null $product_reference
 * @property int|null $parent_expression_id
 * @property int|null $supersedes_id
 * @property string|null $author_reference
 * @property string|null $author_display_name
 * @property int|null $score
 * @property int|null $scale
 * @property string|null $body
 * @property string $locale
 * @property ExpressionSource $source
 * @property string|null $source_reference
 * @property IncentiveDisclosure $incentive
 * @property VerificationState $verification
 * @property CarbonImmutable|null $verified_at
 * @property ScreeningPriority $screening_priority
 * @property int $screening_weight
 * @property list<string>|null $screening_signals
 * @property string|null $live_key
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable|null $superseded_at
 * @property CarbonImmutable|null $redacted_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Collection<int, ExpressionFacet> $facets
 * @property-read Collection<int, ModerationDecision> $decisions
 * @property-read ModerationDecision|null $latestDecision
 * @property-read Collection<int, Vote> $votes
 * @property-read Collection<int, Flag> $flags
 * @property-read Collection<int, Expression> $replies
 * @property-read Expression|null $supersedes
 * @property-read Expression|null $supersededBy
 */
class Expression extends Model
{
    /**
     * Columns that record what was said. Changing one rewrites history, so the
     * model refuses. Note `getRawOriginal()`, not `getOriginal()`: for an
     * enum-cast attribute the latter hands back the enum object, and comparing
     * that to a stored string is silently always unequal — a guard written that
     * way fires on every save, and a guard written the other way round never
     * fires at all.
     */
    private const IMMUTABLE_COLUMNS = [
        'reference', 'tenant_id', 'kind', 'product_reference', 'parent_expression_id',
        'supersedes_id', 'score', 'scale', 'locale', 'source', 'source_reference',
        'incentive', 'verification', 'verified_at', 'occurred_at',
    ];

    /** Content that erasure may remove. It may go to null and nowhere else. */
    private const REDACTABLE_COLUMNS = ['body', 'author_reference', 'author_display_name'];

    protected $table = 'reviews_expressions';

    protected $guarded = [];

    /**
     * Declared here as well as in the migration: `create()` does not read a
     * column default back, so a freshly created model would otherwise carry
     * nulls the database does not hold.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'locale' => 'en',
        'source' => 'first_party',
        'incentive' => 'none',
        'verification' => 'unknown',
        'screening_priority' => 'routine',
        'screening_weight' => 1,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => ExpressionKind::class,
            'source' => ExpressionSource::class,
            'incentive' => IncentiveDisclosure::class,
            'verification' => VerificationState::class,
            'screening_priority' => ScreeningPriority::class,
            'screening_signals' => 'array',
            'screening_weight' => 'integer',
            'score' => 'integer',
            'scale' => 'integer',
            'occurred_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
            'redacted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $expression): void {
            // Opaque and unguessable, so a public surface can name an expression
            // without publishing a row count. No symfony/uid dependency to smuggle in.
            $expression->reference ??= bin2hex(random_bytes(16));
        });

        static::saving(function (self $expression): void {
            $expression->live_key = $expression->liveKey();
            $expression->screening_weight = $expression->screening_priority->weight();
        });

        static::updating(function (self $expression): void {
            $expression->assertOnlyAppendableColumnsChanged();
        });
    }

    /**
     * The natural key of a *live* expression, or null once it is superseded or
     * redacted. Uniquely indexed, so "one live expression per author per
     * product" and "at most one live reply per expression" are enforced by the
     * database rather than by a read the second concurrent writer also passes.
     */
    public function liveKey(): ?string
    {
        if ($this->superseded_at !== null || $this->redacted_at !== null) {
            return null;
        }

        $natural = $this->kind === ExpressionKind::MerchantReply
            ? ['reply', $this->tenant_id, (string) $this->parent_expression_id]
            : ['expression', $this->tenant_id, (string) $this->product_reference, (string) $this->author_reference];

        return hash('sha256', implode("\0", $natural));
    }

    /** Derived from the decisions, never stored. Nothing is displayed by arriving. */
    public function displayState(): DisplayState
    {
        return $this->latestDecision?->outcome->displayState() ?? DisplayState::Pending;
    }

    public function isDisplayed(): bool
    {
        return $this->displayState()->isDisplayed() && $this->superseded_at === null;
    }

    public function isRedacted(): bool
    {
        return $this->redacted_at !== null;
    }

    /** Counted from rows. There is no counter column to race. */
    public function tally(): VoteTally
    {
        $directions = $this->votes->countBy(static fn (Vote $vote): string => $vote->direction->value);

        return new VoteTally(
            (int) $directions->get(VoteDirection::Helpful->value, 0),
            (int) $directions->get(VoteDirection::Unhelpful->value, 0),
        );
    }

    /** The live merchant reply, if a merchant has answered and not withdrawn it. */
    public function liveReply(): ?self
    {
        return $this->replies->first(static fn (self $reply): bool => $reply->superseded_at === null && $reply->redacted_at === null);
    }

    /** @return list<ModerationReason> */
    public function screeningSignals(): array
    {
        return array_values(array_map(
            ModerationReason::from(...),
            $this->screening_signals ?? [],
        ));
    }

    /** @return HasMany<ExpressionFacet, $this> */
    public function facets(): HasMany
    {
        return $this->hasMany(ExpressionFacet::class);
    }

    /** @return HasMany<ModerationDecision, $this> */
    public function decisions(): HasMany
    {
        return $this->hasMany(ModerationDecision::class)->orderBy('occurred_at')->orderBy('id');
    }

    /** @return HasOne<ModerationDecision, $this> */
    public function latestDecision(): HasOne
    {
        return $this->hasOne(ModerationDecision::class)->ofMany(['occurred_at' => 'max', 'id' => 'max']);
    }

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /** @return HasMany<Flag, $this> */
    public function flags(): HasMany
    {
        return $this->hasMany(Flag::class);
    }

    /** @return HasMany<Expression, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_expression_id');
    }

    /** @return BelongsTo<Expression, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /** @return HasOne<Expression, $this> */
    public function supersededBy(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_id');
    }

    private function assertOnlyAppendableColumnsChanged(): void
    {
        $attributes = $this->getAttributes();

        foreach (self::IMMUTABLE_COLUMNS as $column) {
            if (($attributes[$column] ?? null) !== $this->getRawOriginal($column)) {
                throw ExpressionIsImmutable::column((string) $this->getRawOriginal('reference'), $column);
            }
        }

        foreach (self::REDACTABLE_COLUMNS as $column) {
            $current = $attributes[$column] ?? null;

            if ($current !== $this->getRawOriginal($column) && $current !== null) {
                throw ExpressionIsImmutable::column((string) $this->getRawOriginal('reference'), $column);
            }
        }
    }
}
