<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product compliance per market.
 *
 * A missing rule resolves to `unknown` (the safe default): no purchase CTA,
 * no offers, no recommendation (docs/adr/0007-compliance-before-serialization.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_compliance_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->string('status', 24);
            $table->text('reason')->nullable();
            $table->string('source')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewer_label')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'country_id']);
            $table->index(['country_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_compliance_rules');
    }
};
