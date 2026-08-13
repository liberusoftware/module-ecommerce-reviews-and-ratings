<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\FlagReason;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $expression_id
 * @property string|null $reporter_reference
 * @property FlagReason $reason
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Expression $expression
 */
class Flag extends Model
{
    protected $table = 'reviews_flags';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reason' => FlagReason::class,
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Expression, $this> */
    public function expression(): BelongsTo
    {
        return $this->belongsTo(Expression::class);
    }
}
