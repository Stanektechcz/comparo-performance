<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned ComparoRank configuration.
 *
 * Weights are never edited in place: a change is a new version, activated by
 * an audited action. Every rank explanation names the version it used.
 * Allowed factor keys are enforced by App\Domain\Offers\Ranking\RankingFactor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranking_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 64)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        // At most one active version.
        DB::statement('CREATE UNIQUE INDEX ranking_versions_single_active ON ranking_versions (is_active) WHERE is_active = true');

        Schema::create('ranking_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ranking_version_id')->constrained()->cascadeOnDelete();
            $table->string('factor', 32);
            $table->unsignedSmallInteger('weight');
            $table->unsignedTinyInteger('position')->default(0);

            $table->unique(['ranking_version_id', 'factor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_weights');
        Schema::dropIfExists('ranking_versions');
    }
};
