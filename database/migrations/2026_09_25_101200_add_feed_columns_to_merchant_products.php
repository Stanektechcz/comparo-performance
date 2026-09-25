<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Feed identity and matching state on merchant listings.
 *
 * On SQLite, adding foreign keys rebuilds merchant_products (it has no
 * triggers or partial indexes before this migration, so nothing is lost). The
 * review-queue partial index is created last, after the rebuild; any later
 * SQLite rebuild of this table must recreate it (see
 * tests/Feature/Schema/Phase2SchemaTest.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_products', function (Blueprint $table) {
            $table->unsignedBigInteger('feed_source_id')->nullable();
            $table->string('external_id', 191)->nullable();
            $table->string('brand_raw', 128)->nullable();
            $table->string('pack_raw', 64)->nullable();
            $table->string('variant_raw', 128)->nullable();
            $table->string('category_raw', 255)->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->json('raw_payload')->nullable();
            $table->char('content_hash', 64)->nullable();
            // Hash of the facts the matcher reads; unchanged → the current decision is reused.
            $table->char('facts_fingerprint', 64)->nullable();
            $table->string('status', 16)->default('active');
            $table->unsignedTinyInteger('missing_run_count')->default(0);
            $table->string('match_status', 24)->default('unmatched');
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->unsignedBigInteger('last_seen_run_id')->nullable();
        });

        // Listings linked before Phase 2 were linked by the (prototype) importer.
        DB::table('merchant_products')->whereNotNull('product_id')->update(['match_status' => 'auto']);

        Schema::table('merchant_products', function (Blueprint $table) {
            $table->foreign(['feed_source_id', 'merchant_id'])
                ->references(['id', 'merchant_id'])->on('feed_sources')
                ->restrictOnDelete();
            $table->foreign('last_seen_run_id')->references('id')->on('feed_runs')->nullOnDelete();
        });

        Schema::table('merchant_products', function (Blueprint $table) {
            // Target of the composite (merchant_product_id, merchant_id) foreign keys.
            $table->unique(['id', 'merchant_id']);
            // Reconciliation: a source's listings not seen in the latest run.
            $table->index(['feed_source_id', 'status', 'last_seen_run_id'], 'merchant_products_source_status_seen_index');
            $table->index(['feed_source_id', 'external_id']);
            $table->index('last_seen_run_id');
        });

        DB::statement("CREATE INDEX merchant_products_review_queue ON merchant_products (merchant_id, updated_at) WHERE match_status IN ('suggested', 'unmatched')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX merchant_products_review_queue');

        Schema::table('merchant_products', function (Blueprint $table) {
            $table->dropIndex(['last_seen_run_id']);
            $table->dropIndex(['feed_source_id', 'external_id']);
            $table->dropIndex('merchant_products_source_status_seen_index');
            $table->dropUnique(['id', 'merchant_id']);
        });

        Schema::table('merchant_products', function (Blueprint $table) {
            $table->dropForeign(['last_seen_run_id']);
            $table->dropForeign(['feed_source_id', 'merchant_id']);
        });

        Schema::table('merchant_products', function (Blueprint $table) {
            $table->dropColumn([
                'feed_source_id', 'external_id', 'brand_raw', 'pack_raw', 'variant_raw', 'category_raw', 'image_url',
                'raw_payload', 'content_hash', 'facts_fingerprint', 'status', 'missing_run_count', 'match_status',
                'match_score', 'matched_at', 'last_seen_run_id',
            ]);
        });
    }
};
