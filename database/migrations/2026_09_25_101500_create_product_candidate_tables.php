<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * New-product proposals raised from unmatched listings. Creating the canonical
 * product stays a staff catalogue action (Phase 8); a candidate may instead be
 * resolved as an existing product (linked_product_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('status', 16)->default('proposed');
            // Hash of the normalised identity facts (brand, name, pack, EAN).
            $table->char('fingerprint', 64);
            $table->string('proposed_name');
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('brand_raw', 128)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ean', 14)->nullable();
            $table->string('pack_label', 32)->nullable();
            $table->json('evidence')->nullable();
            $table->unsignedSmallInteger('source_count')->default(0);
            $table->foreignId('linked_product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('fingerprint');
        });

        // One open proposal per identity.
        DB::statement("CREATE UNIQUE INDEX product_candidates_single_proposed ON product_candidates (fingerprint) WHERE status = 'proposed'");

        Schema::create('product_candidate_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_candidate_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('merchant_id')->index();
            $table->unsignedBigInteger('merchant_product_id');
            $table->timestamp('first_seen_at');
            $table->timestamps();

            $table->unique(['product_candidate_id', 'merchant_product_id'], 'product_candidate_sources_unique');
            $table->index('merchant_product_id');
            $table->foreign(['merchant_product_id', 'merchant_id'])
                ->references(['id', 'merchant_id'])->on('merchant_products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_candidate_sources');
        Schema::dropIfExists('product_candidates');
    }
};
