<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned product-matching policy (weights, bucket thresholds, level
 * cut-offs), managed exactly like ranking_versions: a change is a new version
 * activated by an audited action, and every matching decision names the policy
 * it used. Read by App\Domain\Matching\Engine\MatchingPolicy::fromArray().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_policies', function (Blueprint $table) {
            $table->id();
            $table->string('version', 64)->unique();
            $table->string('algorithm', 64);
            $table->json('weights');
            $table->json('thresholds');
            $table->json('levels');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        // At most one active policy.
        DB::statement('CREATE UNIQUE INDEX matching_policies_single_active ON matching_policies (is_active) WHERE is_active = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_policies');
    }
};
