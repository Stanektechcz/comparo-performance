<?php

use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Audit\AuditActor;
use App\Domain\Platform\Audit\AuditLogger;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F-03: the system actor's component lives in audit_logs.actor_component
 * (migration 2026_09_26_100100), never inside `after`.
 */
it('stores the system component in its own column and leaves after untouched', function () {
    $log = app(AuditLogger::class)->record(
        AuditAction::FeedSourceStatusChanged,
        AuditActor::system('feeds.pipeline'),
        after: ['status' => 'error'],
    );

    $raw = DB::table('audit_logs')->where('id', $log->id)->first();

    expect($raw->actor_component)->toBe('feeds.pipeline')
        ->and(json_decode((string) $raw->after, true))->toBe(['status' => 'error']);
});

it('stores no component for a user actor', function () {
    $log = app(AuditLogger::class)->record(AuditAction::FeedRunStartedManually, AuditActor::user(User::factory()->create()));

    expect($log->fresh()?->actor_component)->toBeNull()
        ->and($log->fresh()?->after)->toBeNull();
});

it('keeps audit_logs append-only after the column is added', function () {
    app(AuditLogger::class)->record(AuditAction::FeedRunCancelled, AuditActor::system('feeds.scheduler'));

    expect(Schema::hasColumn('audit_logs', 'actor_component'))->toBeTrue()
        ->and(fn () => DB::transaction(fn () => DB::table('audit_logs')->update(['actor_component' => 'tampered'])))->toThrow(QueryException::class)
        ->and(AuditLog::query()->sole()->actor_component)->toBe('feeds.scheduler');
});

it('drops the column and keeps the triggers when rolled back', function () {
    $migration = require database_path('migrations/2026_09_26_100100_add_actor_component_to_audit_logs.php');

    $migration->down();
    expect(Schema::hasColumn('audit_logs', 'actor_component'))->toBeFalse();

    AuditLog::create(['actor_type' => 'system', 'action' => 'test.recorded']);
    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->delete()))->toThrow(QueryException::class);

    $migration->up();
    expect(Schema::hasColumn('audit_logs', 'actor_component'))->toBeTrue();
});
