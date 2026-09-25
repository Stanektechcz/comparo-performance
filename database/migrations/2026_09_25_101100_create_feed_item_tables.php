<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-run staging rows and structured feed errors.
 *
 * feed_items are pruned by a retention job; feed_errors outlive them (the
 * item reference is nulled, row_number is kept). Error messages are rendered
 * from translations using `code` + `message_params` — no free text is stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('merchant_id')->index();
            $table->unsignedInteger('row_number');
            $table->string('merchant_sku', 128)->nullable();
            $table->string('external_id', 191)->nullable();
            $table->string('ean', 14)->nullable();
            $table->string('title', 512)->nullable();
            $table->string('brand_raw', 128)->nullable();
            $table->string('pack_raw', 64)->nullable();
            $table->string('variant_raw', 128)->nullable();
            $table->string('category_raw', 255)->nullable();
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->unsignedBigInteger('reference_price_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('availability', 16)->nullable();
            $table->integer('stock_quantity')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->json('raw_payload')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->string('validation_status', 16);
            $table->string('match_status', 24)->default('pending');
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->json('match_parts')->nullable();
            $table->foreignId('suggested_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('merchant_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('diff_action', 16)->nullable();
            $table->timestamps();

            $table->unique(['feed_run_id', 'row_number']);
            $table->index(['feed_run_id', 'match_status']);
            $table->index(['feed_run_id', 'validation_status']);
            $table->index(['merchant_id', 'merchant_sku']);
            $table->index('merchant_product_id');
        });

        Schema::create('feed_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('merchant_id')->index();
            // Errors must survive item pruning.
            $table->foreignId('feed_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('row_number')->nullable();
            $table->string('code', 64);
            $table->string('severity', 16);
            $table->string('field', 64)->nullable();
            $table->json('message_params')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['feed_run_id', 'severity']);
            $table->index(['feed_run_id', 'code']);
            // Item pruning nulls this reference (PostgreSQL does not index FKs itself).
            $table->index('feed_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_errors');
        Schema::dropIfExists('feed_items');
    }
};
