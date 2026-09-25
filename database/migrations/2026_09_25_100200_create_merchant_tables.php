<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchants, their team membership, shipping zones and trust inputs.
 *
 * Commercial data (plan, tier, affiliate commission) deliberately does not
 * live here: it belongs to the Commercial/Affiliate contexts and must never be
 * reachable from ranking (docs/adr/0004-ranking-purity.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('website')->nullable();
            $table->char('home_country_code', 2)->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('free_shipping_threshold_minor')->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->unsignedSmallInteger('return_days')->nullable();
            $table->text('description')->nullable();
            // Aggregate rating as received/computed; recomputed by the Reviews context.
            $table->decimal('rating_average', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            // Credibility-weighted public rating (derived; see docs/modules/reviews.md).
            $table->decimal('weighted_rating', 3, 1)->nullable();
            $table->timestamps();
        });

        Schema::create('merchant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('owner');
            $table->timestamps();

            $table->unique(['merchant_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('merchant_shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('cost_minor');
            $table->char('currency', 3);
            $table->unsignedTinyInteger('min_days');
            $table->unsignedTinyInteger('max_days');
            $table->string('carrier', 64)->nullable();
            $table->boolean('duties_apply')->default(false);
            $table->timestamps();

            $table->unique(['merchant_id', 'country_id']);
            $table->index('country_id');
        });

        // Measured trust inputs, append-only; the latest row per merchant is current.
        Schema::create('merchant_trust_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->boolean('business_verified')->default(false);
            $table->unsignedInteger('account_age_days')->nullable();
            $table->decimal('verified_review_ratio', 5, 2)->nullable();
            $table->decimal('complaint_rate', 5, 2)->nullable();
            $table->decimal('complaint_resolution_rate', 5, 2)->nullable();
            $table->decimal('response_rate', 5, 2)->nullable();
            $table->decimal('verified_order_rate', 5, 2)->nullable();
            $table->decimal('price_accuracy', 5, 2)->nullable();
            $table->decimal('feed_uptime', 5, 2)->nullable();
            $table->decimal('shipping_accuracy', 5, 2)->nullable();
            $table->decimal('broken_link_rate', 5, 2)->nullable();
            $table->unsignedInteger('community_reports')->default(0);
            $table->decimal('delivery_on_time', 5, 2)->nullable();
            $table->string('source', 32);
            $table->timestamp('measured_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['merchant_id', 'measured_at']);
        });

        // Internal integrity events. Never exposed to merchants or the public API.
        Schema::create('merchant_risk_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 64);
            $table->string('severity', 16);
            $table->text('description')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['merchant_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_risk_events');
        Schema::dropIfExists('merchant_trust_signals');
        Schema::dropIfExists('merchant_shipping_zones');
        Schema::dropIfExists('merchant_user');
        Schema::dropIfExists('merchants');
    }
};
