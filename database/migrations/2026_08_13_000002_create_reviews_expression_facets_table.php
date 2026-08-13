<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The breakdown scores, as rows.
 *
 * The host holds these as three nullable columns and then divides their sum by
 * four, so a rating carrying only an overall five reads as 1.25 stars. A facet
 * that was not given is a row that does not exist, which no arithmetic can
 * mistake for a zero. Each carries its own scale, because a facet is a rating.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('reviews_expression_facets', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('expression_id')->constrained('reviews_expressions')->cascadeOnDelete();
            $table->string('facet', 32);
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('scale');
            $table->timestamps();

            $table->unique(['expression_id', 'facet']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews_expression_facets');
    }
};
