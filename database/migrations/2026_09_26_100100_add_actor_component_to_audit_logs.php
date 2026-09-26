<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F-03: a system actor's component (e.g. `feeds.scheduler`) gets its own
 * column instead of being written into `after._actor_component`.
 *
 * audit_logs is append-only (trigger), so existing rows are not rewritten:
 * rows written before this migration keep the component in `after`.
 *
 * Adding or dropping a nullable column does not rebuild the table on
 * SQLite, but the append-only triggers are re-asserted after the change
 * anyway (same SQL as 2026_09_25_100700_create_audit_logs_table), so a
 * rebuild on another SQLite version can never leave the table unprotected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('actor_component', 96)->nullable()->after('actor_type');
        });

        $this->restoreSqliteAppendOnlyTriggers();
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('actor_component');
        });

        $this->restoreSqliteAppendOnlyTriggers();
    }

    private function restoreSqliteAppendOnlyTriggers(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');
        DB::unprepared("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit_logs is append-only'); END");
        DB::unprepared("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit_logs is append-only'); END");
    }
};
