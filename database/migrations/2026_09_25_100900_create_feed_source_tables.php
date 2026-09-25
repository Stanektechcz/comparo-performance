<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merchant feed sources and their versioned field mappings
 * (docs/architecture/phase-2-feeds-matching.md §2).
 *
 * Tenant isolation: every child row carries merchant_id and references its
 * source through the composite key (id, merchant_id), so a row can never point
 * at another merchant's source. Credentials are stored encrypted by the model
 * cast and never serialized.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->string('name', 96);
            $table->string('format', 8);
            $table->string('transport', 16);
            $table->string('status', 16)->default('draft');
            $table->string('status_reason', 64)->nullable();
            $table->string('url', 2048)->nullable();
            $table->text('credentials')->nullable();
            // Market the feed serves; null = all of the merchant's markets.
            $table->foreignId('country_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->string('encoding', 32)->default('UTF-8');
            $table->string('delimiter', 4)->nullable();
            $table->string('record_element', 64)->nullable();
            $table->json('availability_map')->nullable();
            $table->unsignedInteger('interval_minutes')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->char('last_checksum', 64)->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamps();

            // Target of the composite (feed_source_id, merchant_id) foreign keys.
            $table->unique(['id', 'merchant_id']);
            $table->unique(['merchant_id', 'name']);
            // Scheduler: due active sources.
            $table->index(['status', 'next_run_at']);
        });

        Schema::create('feed_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('feed_source_id');
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedInteger('version');
            $table->json('field_map');
            $table->boolean('is_current')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['feed_source_id', 'version']);
            $table->index('merchant_id');
            $table->foreign(['feed_source_id', 'merchant_id'])
                ->references(['id', 'merchant_id'])->on('feed_sources')
                ->restrictOnDelete();
        });

        // At most one current mapping per source.
        DB::statement('CREATE UNIQUE INDEX feed_mappings_single_current ON feed_mappings (feed_source_id) WHERE is_current = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_mappings');
        Schema::dropIfExists('feed_sources');
    }
};
