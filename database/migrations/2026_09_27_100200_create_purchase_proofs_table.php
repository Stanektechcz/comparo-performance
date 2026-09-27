<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase proofs (docs/architecture/phase-4-reviews-orders.md §2, §5; A-35,
 * A-38). evidence holds metadata only — never the document or the forwarded
 * e-mail. The order reference is stored as an HMAC and the receipt as a
 * sha256 so duplicates across accounts are detectable without keeping the
 * source; receipt_path points into the private `receipts` disk and is
 * cleared when the receipt is purged (receipt_purged_at, ≤ 30 days).
 *
 * order_id is the order created from this evidence (orders only from
 * evidence, D-15; one order per proof). User references are SET NULL for
 * erasure; every other foreign key is RESTRICT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('review_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('method', 32);
            $table->string('status', 16)->default('pending');
            $table->json('evidence')->nullable();
            $table->char('order_reference_hash', 64)->nullable();
            $table->char('receipt_sha256', 64)->nullable();
            $table->string('receipt_path')->nullable();
            $table->timestamp('receipt_purged_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_code', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('user_id');
            $table->index('review_id');
            $table->index('merchant_id');
            $table->index('order_reference_hash');
            $table->index('receipt_sha256');
            $table->index('decided_by_user_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_proofs');
    }
};
