<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The real-review rating projection (docs/architecture/phase-4-reviews-orders.md
 * §2, §7; A-31, A-33, A-37): one row per product or merchant, recomputed by the
 * Reviews context from approved reviews only (source `aggregated`). Imported
 * demo ratings stay on products/merchants and are never blended in here.
 *
 * Exactly one subject per row (CHECK written inline in CREATE TABLE so both
 * drivers enforce it) and at most one row per subject. Foreign keys are
 * RESTRICT: products and merchants are retired/suspended, never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_aggregates', function (Blueprint $table) {
            $table->id();
            $table->rawColumn('subject_type', "varchar(16) check ((subject_type = 'product' and product_id is not null and merchant_id is null) or (subject_type = 'merchant' and merchant_id is not null and product_id is null))");
            $table->foreignId('product_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('merchant_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('source', 24)->default('aggregated');
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('verified_count')->default(0);
            $table->unsignedInteger('recommend_count')->default(0);
            $table->decimal('rating_average', 3, 2)->nullable();
            $table->decimal('weighted_rating', 3, 1)->nullable();
            $table->decimal('weight_sum', 10, 3)->default(0);
            $table->json('distribution')->nullable();
            $table->json('sub_ratings')->nullable();
            $table->string('algorithm_version', 32);
            $table->timestamp('computed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_aggregates');
    }
};
