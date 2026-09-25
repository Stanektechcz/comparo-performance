<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Index (and, on PostgreSQL, foreign key) for price_snapshots.feed_run_id.
 *
 * SQLite: index only. Adding a foreign key would rebuild price_snapshots and
 * drop its append-only triggers. PostgreSQL: the constraint is added
 * NOT VALID and validated separately, so existing rows are checked without
 * holding an ACCESS EXCLUSIVE lock for the scan. RESTRICT, never SET NULL:
 * an ON DELETE SET NULL would issue an UPDATE the append-only trigger rejects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_snapshots', function (Blueprint $table) {
            $table->index('feed_run_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE price_snapshots ADD CONSTRAINT price_snapshots_feed_run_id_foreign FOREIGN KEY (feed_run_id) REFERENCES feed_runs (id) ON DELETE RESTRICT NOT VALID');
            DB::statement('ALTER TABLE price_snapshots VALIDATE CONSTRAINT price_snapshots_feed_run_id_foreign');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE price_snapshots DROP CONSTRAINT price_snapshots_feed_run_id_foreign');
        }

        Schema::table('price_snapshots', function (Blueprint $table) {
            $table->dropIndex(['feed_run_id']);
        });
    }
};
