<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per voter per expression, uniquely indexed.
 *
 * The host increments a counter with no lock and records nobody, so one account
 * can buy the sort order of a product page with curl in a while loop. Totals
 * here are a count of rows; changing your mind updates your row.
 *
 * `voter_reference` is nullable only so erasure can redact it without deleting
 * the vote — a null does not collide in a unique index, and the total a shopper
 * sees does not move because somebody exercised their rights.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('reviews_votes', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('expression_id')->constrained('reviews_expressions')->cascadeOnDelete();
            $table->string('voter_reference')->nullable();
            $table->string('direction', 16);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['expression_id', 'voter_reference']);
            $table->index(['tenant_id', 'voter_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews_votes');
    }
};
