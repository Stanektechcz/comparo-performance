<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F-01: merchant_trust_signals is trust history, append-only like
 * price_snapshots and audit_logs.
 *
 * - The database now rejects every UPDATE and DELETE (a trigger on both
 *   supported drivers; PostgreSQL reuses comparo_forbid_mutation(), created
 *   by 2026_09_25_100500_create_price_history_tables).
 * - merchant_id no longer cascades: merchants are suspended, never deleted,
 *   and deleting one with trust history must fail instead of silently
 *   erasing that history (ON DELETE RESTRICT).
 *
 * On SQLite changing a foreign key rebuilds the table, which drops its
 * triggers, so the triggers are created after the rebuild (the index on
 * merchant_id, measured_at is carried over by the rebuild).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_trust_signals', function (Blueprint $table) {
            $table->dropForeign(['merchant_id']);
            $table->foreign('merchant_id')->references('id')->on('merchants')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('CREATE TRIGGER merchant_trust_signals_append_only BEFORE UPDATE OR DELETE ON merchant_trust_signals FOR EACH ROW EXECUTE FUNCTION comparo_forbid_mutation()');

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER merchant_trust_signals_no_update BEFORE UPDATE ON merchant_trust_signals BEGIN SELECT RAISE(ABORT, 'merchant_trust_signals is append-only'); END");
            DB::unprepared("CREATE TRIGGER merchant_trust_signals_no_delete BEFORE DELETE ON merchant_trust_signals BEGIN SELECT RAISE(ABORT, 'merchant_trust_signals is append-only'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS merchant_trust_signals_append_only ON merchant_trust_signals');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS merchant_trust_signals_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS merchant_trust_signals_no_delete');
        }

        Schema::table('merchant_trust_signals', function (Blueprint $table) {
            $table->dropForeign(['merchant_id']);
            $table->foreign('merchant_id')->references('id')->on('merchants')->cascadeOnDelete();
        });
    }
};
