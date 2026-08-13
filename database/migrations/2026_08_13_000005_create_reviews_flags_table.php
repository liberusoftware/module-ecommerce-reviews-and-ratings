<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A reader's report. Its reason is a closed enum, for the same reason a moderation reason is. */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('reviews_flags', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('expression_id')->constrained('reviews_expressions')->cascadeOnDelete();
            $table->string('reporter_reference')->nullable();
            $table->string('reason', 48);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['expression_id', 'reporter_reference']);
            $table->index(['tenant_id', 'reporter_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews_flags');
    }
};
