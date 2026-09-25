<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff matching-conflict queue: sources disagreeing about a product fact,
 * compliance holds and blocked merges, with the competing observed values.
 *
 * At most one open conflict per (product, kind, field). NULL fields never
 * collide in a unique index on either driver, so field-less kinds
 * (compliance_hold, merge_blocked) are de-duplicated by the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('kind', 24);
            $table->string('field', 32)->nullable();
            $table->string('status', 16)->default('open');
            $table->string('resolved_value')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('product_id');
        });

        DB::statement("CREATE UNIQUE INDEX matching_conflicts_single_open ON matching_conflicts (product_id, kind, field) WHERE status = 'open'");

        Schema::create('matching_conflict_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matching_conflict_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merchant_product_id')->nullable()->constrained()->nullOnDelete();
            // catalogue | merchant_feed | brand | staff …
            $table->string('source_type', 24);
            $table->unsignedTinyInteger('source_priority');
            $table->string('value', 255);
            $table->unsignedInteger('observed_count')->default(1);
            $table->timestamps();

            $table->unique(['matching_conflict_id', 'source_type', 'merchant_product_id', 'value'], 'matching_conflict_values_unique');
            $table->index('merchant_product_id');
            $table->index('merchant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_conflict_values');
        Schema::dropIfExists('matching_conflicts');
    }
};
