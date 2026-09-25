<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Offer lifecycle columns for feed publishing and reconciliation.
 *
 * SQLite: columns are added with plain ADD COLUMN and last_feed_run_id gets
 * no foreign key — adding one would rebuild `offers` and silently turn the
 * partial `offers_product_active_index` into a full index. PostgreSQL gets
 * the foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable();
            $table->string('deactivation_reason', 32)->nullable();
            // feed | prototype_demo | manual …
            $table->string('source', 24)->default('feed');
            $table->unsignedBigInteger('last_feed_run_id')->nullable();

            $table->index('last_feed_run_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            Schema::table('offers', function (Blueprint $table) {
                $table->foreign('last_feed_run_id')->references('id')->on('feed_runs')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('offers', function (Blueprint $table) {
                $table->dropForeign(['last_feed_run_id']);
            });
        }

        Schema::table('offers', function (Blueprint $table) {
            $table->dropIndex(['last_feed_run_id']);
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['deactivated_at', 'deactivation_reason', 'source', 'last_feed_run_id']);
        });
    }
};
