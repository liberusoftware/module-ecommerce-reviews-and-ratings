<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only. One row per decision, never updated.
 *
 * The host stores this as a boolean two controller methods flip in place, so a
 * review approved, retracted and re-approved is indistinguishable from one
 * approved once, and nobody can say who did any of it. There is deliberately no
 * free-text note column: a moderation reason sits beside a person's name by
 * construction, so it is a closed enum.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('reviews_moderation_decisions', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('expression_id')->constrained('reviews_expressions')->cascadeOnDelete();
            $table->string('outcome', 32);
            $table->string('reason', 48);
            $table->string('actor_reference');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['expression_id', 'occurred_at']);
            $table->index(['tenant_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews_moderation_decisions');
    }
};
