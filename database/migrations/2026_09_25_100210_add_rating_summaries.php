<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Derived review summary on the product (credibility-weighted average).
 * Recomputed by the Reviews context (Phase 4); never edited by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('weighted_rating', 3, 1)->nullable()->after('dose_updated_at');
            $table->unsignedInteger('rating_count')->default(0)->after('weighted_rating');
            $table->string('rating_source', 24)->nullable()->after('rating_count');
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->string('rating_source', 24)->nullable()->after('weighted_rating');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('rating_source');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['weighted_rating', 'rating_count', 'rating_source']);
        });
    }
};
