<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decouples audit_logs.actor_id from users.
 *
 * The original foreign key used ON DELETE SET NULL, which issues an UPDATE on
 * audit_logs when a user is deleted — and the append-only trigger rejects
 * every UPDATE, so users with audit history could not delete their account.
 * The audit trail keeps the (pseudonymous) actor id instead; the index stays.
 *
 * On SQLite dropping a foreign key rebuilds the table, which drops its
 * triggers, so the append-only triggers are recreated here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['actor_id']);
        });

        $this->restoreSqliteAppendOnlyTriggers();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // NOT VALID: rows whose actor was deleted meanwhile must not block the rollback.
            DB::statement('ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_actor_id_foreign FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL NOT VALID');

            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
        });

        $this->restoreSqliteAppendOnlyTriggers();
    }

    /**
     * Same trigger SQL as 2026_09_25_100700_create_audit_logs_table.
     */
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
