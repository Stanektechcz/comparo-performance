<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R3: audit_logs.actor_id used to reference users ON DELETE SET NULL, which
 * collides with the append-only trigger. Migration 2026_09_25_100750 drops
 * that foreign key; the audit row keeps the pseudonymous actor id.
 */
function auditRowFor(User $user): AuditLog
{
    return AuditLog::create(['actor_id' => $user->id, 'actor_type' => 'user', 'action' => 'test.recorded']);
}

/**
 * @return array<int, mixed>
 */
function sqliteAuditTriggers(): array
{
    return DB::table('sqlite_master')
        ->where('type', 'trigger')
        ->where('tbl_name', 'audit_logs')
        ->orderBy('name')
        ->pluck('name')
        ->all();
}

it('lets a user with audit history delete their account and keeps the audit row', function () {
    $user = User::factory()->create();
    $log = auditRowFor($user);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    expect($user->fresh())->toBeNull()
        ->and(AuditLog::query()->find($log->id)?->actor_id)->toBe($user->id);
});

it('deletes a user model with audit rows and keeps the actor id', function () {
    $user = User::factory()->create();
    $log = auditRowFor($user);

    $user->delete();

    expect(User::query()->find($user->id))->toBeNull()
        ->and(AuditLog::query()->find($log->id)?->actor_id)->toBe($user->id);
});

it('has no foreign key on audit_logs but keeps the actor index', function () {
    $indexedColumns = collect(Schema::getIndexes('audit_logs'))->pluck('columns')->all();

    expect(Schema::getForeignKeys('audit_logs'))->toBe([])
        ->and($indexedColumns)->toContain(['actor_id']);
});

it('still rejects raw updates and deletes on audit_logs after the table rebuild', function () {
    if (DB::getDriverName() === 'sqlite') {
        expect(sqliteAuditTriggers())->toBe(['audit_logs_no_delete', 'audit_logs_no_update']);
    }

    auditRowFor(User::factory()->create());

    // Each attempt runs in its own savepoint: PostgreSQL aborts the whole transaction on error.
    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->update(['action' => 'tampered'])))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('audit_logs')->delete()))->toThrow(QueryException::class)
        ->and(DB::table('audit_logs')->value('action'))->toBe('test.recorded');
});

it('restores the foreign key and the triggers when rolled back', function () {
    $migration = require database_path('migrations/2026_09_25_100750_decouple_audit_log_actor_from_users.php');

    $migration->down();

    expect(collect(Schema::getForeignKeys('audit_logs'))->pluck('foreign_table')->all())->toBe(['users']);
    if (DB::getDriverName() === 'sqlite') {
        expect(sqliteAuditTriggers())->toBe(['audit_logs_no_delete', 'audit_logs_no_update']);
    }

    // With the foreign key back, R3 reappears: ON DELETE SET NULL collides with the trigger.
    $user = User::factory()->create();
    auditRowFor($user);
    expect(fn () => DB::transaction(fn () => $user->delete()))->toThrow(QueryException::class);

    $migration->up();

    expect(Schema::getForeignKeys('audit_logs'))->toBe([]);
    if (DB::getDriverName() === 'sqlite') {
        expect(sqliteAuditTriggers())->toBe(['audit_logs_no_delete', 'audit_logs_no_update']);
    }
});
