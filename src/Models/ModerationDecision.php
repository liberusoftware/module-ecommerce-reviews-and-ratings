<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationOutcome;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\ModerationReason;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $expression_id
 * @property ModerationOutcome $outcome
 * @property ModerationReason $reason
 * @property string $actor_reference
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class ModerationDecision extends Model
{
    protected $table = 'reviews_moderation_decisions';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'outcome' => ModerationOutcome::class,
            'reason' => ModerationReason::class,
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
