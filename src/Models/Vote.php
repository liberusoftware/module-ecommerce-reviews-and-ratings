<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\VoteDirection;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $expression_id
 * @property string|null $voter_reference
 * @property VoteDirection $direction
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class Vote extends Model
{
    protected $table = 'reviews_votes';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'direction' => VoteDirection::class,
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
