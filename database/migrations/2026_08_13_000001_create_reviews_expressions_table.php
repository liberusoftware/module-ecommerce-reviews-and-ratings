<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One expression: one person, one moment, one opinion.
 *
 * The host splits this across `product_reviews` and `product_rating`, joined by
 * coincidence rather than a key, and averages two different columns that hold
 * the same number. A star and a sentence are one record here.
 *
 * `live_key` is what closes the host's check-then-act duplicate hole. It is a
 * hash of the natural key of a *live* expression, nulled the moment the row is
 * superseded or redacted, and uniquely indexed — so the database refuses the
 * second of two concurrent writers instead of a read that both of them pass.
 * Hashed rather than concatenated so its width is fixed and no reference lands
 * in an index in the clear.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('reviews_expressions', function (Blueprint $table): void {
            $table->id();
            $table->char('reference', 32)->unique();
            $table->string('tenant_id')->index();
            $table->string('kind', 32);

            // Opaque. This module never joins to a catalogue or an identity store.
            $table->string('product_reference')->nullable();
            $table->foreignId('parent_expression_id')->nullable()->constrained('reviews_expressions')->cascadeOnDelete();
            $table->foreignId('supersedes_id')->nullable()->unique()->constrained('reviews_expressions')->nullOnDelete();
            $table->string('author_reference')->nullable();
            $table->string('author_display_name')->nullable();

            // Integer on a fixed scale, and the scale is part of the record.
            $table->unsignedSmallInteger('score')->nullable();
            $table->unsignedSmallInteger('scale')->nullable();
            $table->text('body')->nullable();
            $table->string('locale', 16)->default('en');

            $table->string('source', 32)->default('first_party');
            $table->string('source_reference')->nullable();
            $table->string('incentive', 32)->default('none');

            $table->string('verification', 16)->default('unknown');
            $table->timestamp('verified_at')->nullable();

            $table->string('screening_priority', 16)->default('routine');

            // Derived from screening_priority and written with it. The queue has
            // to order by urgency, and an enum's string values do not sort into
            // their own meaning — ordering on the label would put `routine` above
            // `elevated`. Kept as a column rather than a raw CASE so the ordering
            // survives an index.
            $table->unsignedTinyInteger('screening_weight')->default(1);
            $table->json('screening_signals')->nullable();

            $table->char('live_key', 64)->nullable()->unique();

            $table->timestamp('occurred_at');
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('redacted_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'product_reference', 'kind']);
            $table->index(['tenant_id', 'author_reference']);
            $table->index(['tenant_id', 'kind', 'superseded_at']);
            $table->index(['tenant_id', 'screening_weight']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews_expressions');
    }
};
