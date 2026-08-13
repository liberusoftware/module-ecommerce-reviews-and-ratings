<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\ReviewsAndRatings\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\ReviewsAndRatings\Enums\FacetKind;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $expression_id
 * @property FacetKind $facet
 * @property int $score
 * @property int $scale
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class ExpressionFacet extends Model
{
    protected $table = 'reviews_expression_facets';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'facet' => FacetKind::class,
            'score' => 'integer',
            'scale' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
