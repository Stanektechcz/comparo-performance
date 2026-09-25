<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merchant listings (feed identity), offers and coupons.
 *
 * A merchant listing is not a canonical product: merchant_products maps a
 * merchant SKU to at most one canonical product. Offers carry the current
 * price; every observed change is appended to price_snapshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('merchant_sku', 128);
            $table->string('title')->nullable();
            $table->string('ean', 14)->nullable();
            $table->string('url', 2048)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'merchant_sku']);
            $table->index('product_id');
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_product_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->string('variant_label', 64)->nullable();
            $table->string('pack_label', 32)->nullable();
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3);
            $table->unsignedBigInteger('reference_price_minor')->nullable();
            $table->timestamp('reference_price_raised_at')->nullable();
            $table->string('availability', 16);
            $table->unsignedInteger('stock_quantity')->nullable();
            $table->char('warehouse_country_code', 2)->nullable();
            $table->string('url', 2048);
            // Price integrity flag raised by anomaly detection (too_low / too_high).
            $table->string('anomaly', 16)->nullable();
            $table->unsignedBigInteger('anomaly_reference_minor')->nullable();
            $table->string('link_status', 16)->default('ok');
            $table->boolean('is_active')->default(true);
            // When the merchant/feed last confirmed this price (drives freshness).
            $table->timestamp('source_updated_at');
            $table->timestamps();

            $table->index(['merchant_id', 'updated_at']);
            $table->index(['product_id', 'merchant_id']);
        });

        // Hot path: active offers of one product.
        DB::statement('CREATE INDEX offers_product_active_index ON offers (product_id) WHERE is_active = true');

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('title')->nullable();
            $table->string('type', 16);
            $table->decimal('percent_off', 5, 2)->nullable();
            $table->unsignedBigInteger('amount_off_minor')->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('min_order_minor')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at');
            $table->boolean('is_exclusive')->default(false);
            $table->string('verification_state', 16)->default('unverified');
            $table->timestamp('last_verified_at')->nullable();
            $table->string('verified_by')->nullable();
            $table->unsignedInteger('reports_worked')->default(0);
            $table->unsignedInteger('reports_failed')->default(0);
            $table->timestamps();

            $table->index(['merchant_id', 'ends_at']);
            $table->index(['merchant_id', 'code']);
        });

        Schema::create('coupon_country', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();

            $table->primary(['coupon_id', 'country_id']);
            $table->index('country_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_country');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('offers');
        Schema::dropIfExists('merchant_products');
    }
};
