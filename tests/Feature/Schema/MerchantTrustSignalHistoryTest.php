<?php

use App\Models\Merchant;
use App\Models\MerchantTrustSignal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F-01: merchant trust history is append-only at the database level and a
 * merchant with history can never be deleted (merchants are suspended), so
 * the history is never cascade-deleted. Migration 2026_09_26_100000 rebuilds
 * the table on SQLite, so its triggers and indexes are checked too.
 * Written to run on SQLite and PostgreSQL.
 */

/**
 * Runs a write inside a savepoint and reports whether the database rejected
 * it (PostgreSQL aborts the whole test transaction otherwise).
 */
function trustHistoryRejects(Closure $write): bool
{
    try {
        DB::transaction($write);
    } catch (QueryException) {
        return true;
    }

    return false;
}

/**
 * @return list<string>
 */
function trustSignalTriggerNames(): array
{
    $names = match (DB::getDriverName()) {
        'sqlite' => DB::table('sqlite_master')->where('type', 'trigger')->where('tbl_name', 'merchant_trust_signals')->pluck('name'),
        'pgsql' => DB::table('information_schema.triggers')->where('event_object_table', 'merchant_trust_signals')->distinct()->pluck('trigger_name'),
        default => collect(),
    };

    return $names->map(static fn (mixed $name): string => (string) $name)->sort()->values()->all();
}

it('rejects raw updates and deletes of trust signals at the database level', function () {
    $signal = MerchantTrustSignal::factory()->create(['community_reports' => 3]);

    expect(trustHistoryRejects(fn () => DB::table('merchant_trust_signals')->where('id', $signal->id)->update(['community_reports' => 0])))->toBeTrue()
        ->and(trustHistoryRejects(fn () => DB::table('merchant_trust_signals')->where('id', $signal->id)->delete()))->toBeTrue()
        ->and(DB::table('merchant_trust_signals')->where('id', $signal->id)->value('community_reports'))->toBe(3);
});

it('refuses to delete a merchant that has trust history instead of cascading', function () {
    $merchant = Merchant::factory()->create();
    MerchantTrustSignal::factory()->count(2)->create(['merchant_id' => $merchant->id]);

    expect(trustHistoryRejects(fn () => DB::table('merchants')->where('id', $merchant->id)->delete()))->toBeTrue()
        ->and(MerchantTrustSignal::query()->where('merchant_id', $merchant->id)->count())->toBe(2)
        ->and(Merchant::query()->whereKey($merchant->id)->exists())->toBeTrue();
});

it('still deletes a merchant without trust history', function () {
    $merchant = Merchant::factory()->create();

    DB::table('merchants')->where('id', $merchant->id)->delete();

    expect(Merchant::query()->whereKey($merchant->id)->exists())->toBeFalse();
});

it('keeps the append-only triggers, the foreign key and the index after the table rebuild', function () {
    $foreignKey = collect(Schema::getForeignKeys('merchant_trust_signals'))->sole();
    $indexedColumns = collect(Schema::getIndexes('merchant_trust_signals'))->pluck('columns')->all();

    expect($foreignKey['columns'])->toBe(['merchant_id'])
        ->and($foreignKey['foreign_table'])->toBe('merchants')
        ->and(strtolower((string) $foreignKey['on_delete']))->toBe('restrict')
        ->and($indexedColumns)->toContain(['merchant_id', 'measured_at'])
        ->and(trustSignalTriggerNames())->toBe(DB::getDriverName() === 'pgsql'
            ? ['merchant_trust_signals_append_only']
            : ['merchant_trust_signals_no_delete', 'merchant_trust_signals_no_update']);
});

it('restores the cascading foreign key without triggers when rolled back, and reapplies cleanly', function () {
    $migration = require database_path('migrations/2026_09_26_100000_make_merchant_trust_signals_append_only.php');

    $migration->down();

    expect(strtolower((string) collect(Schema::getForeignKeys('merchant_trust_signals'))->sole()['on_delete']))->toBe('cascade')
        ->and(trustSignalTriggerNames())->toBe([]);

    $migration->up();

    expect(strtolower((string) collect(Schema::getForeignKeys('merchant_trust_signals'))->sole()['on_delete']))->toBe('restrict')
        ->and(trustSignalTriggerNames())->not->toBe([])
        ->and(collect(Schema::getIndexes('merchant_trust_signals'))->pluck('columns')->all())->toContain(['merchant_id', 'measured_at']);
});
