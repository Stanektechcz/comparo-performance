<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Search analytics, private by design (docs/architecture/phase-3-search.md §6,
 * OPEN-DECISIONS A-24): no IP address and no user id are ever stored. Queries
 * are normalised + redacted, sessions are a daily-rotating salted hash that
 * is nulled after 90 days, and demand is aggregated per day and market.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_queries', function (Blueprint $table) {
            $table->ulid('search_id')->primary();
            $table->timestamp('occurred_at');
            $table->char('market', 2);
            $table->string('locale', 12);
            $table->string('source', 16);
            $table->string('query_normalized', 100);
            $table->char('query_hash', 64);
            $table->json('filters')->nullable();
            $table->unsignedInteger('result_count');
            $table->json('result_refs')->nullable();
            $table->char('session_hash', 64)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index('occurred_at');
            $table->index(['market', 'occurred_at']);
            $table->index('query_hash');
            $table->index('session_hash');
        });

        Schema::create('search_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('search_id')->constrained('search_queries', 'search_id')->cascadeOnDelete();
            $table->string('entity_type', 24);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedSmallInteger('position');
            $table->timestamp('clicked_at');

            $table->unique(['search_id', 'entity_type', 'entity_id']);
        });

        Schema::create('search_demand_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->char('market', 2);
            $table->char('query_hash', 64);
            $table->string('query_normalized', 100);
            $table->unsignedInteger('searches')->default(0);
            $table->unsignedInteger('zero_results')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('sessions')->default(0);
            $table->timestamps();

            $table->unique(['date', 'market', 'query_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_demand_daily');
        Schema::dropIfExists('search_clicks');
        Schema::dropIfExists('search_queries');
    }
};
