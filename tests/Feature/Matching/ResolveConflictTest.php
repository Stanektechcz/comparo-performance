<?php

use App\Domain\Matching\Actions\MatchingActor;
use App\Domain\Matching\Actions\ResolveConflict;
use App\Domain\Matching\ConflictStatus;
use App\Domain\Matching\Exceptions\MatchDecisionNotAllowed;
use App\Domain\Platform\Audit\AuditAction;
use App\Models\AuditLog;
use App\Models\MatchingConflict;
use App\Models\User;
use Tests\Feature\Matching\MatchingScenario as Scenario;

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
});

it('resolves or dismisses an open conflict with a note and audits it', function (ConflictStatus $resolution) {
    $conflict = MatchingConflict::factory()->create(['status' => ConflictStatus::Open]);
    $staff = User::factory()->create();

    $closed = app(ResolveConflict::class)->handle($conflict, $resolution, MatchingActor::staff($staff), Scenario::at(), 'Checked with brand', '900 g');

    expect($closed->only(['status', 'resolved_by_user_id', 'resolution_note', 'resolved_value']))
        ->toBe(['status' => $resolution, 'resolved_by_user_id' => $staff->id, 'resolution_note' => 'Checked with brand', 'resolved_value' => '900 g'])
        ->and($closed->resolved_at->toDateTimeString())->toBe('2026-09-25 11:00:00')
        ->and(AuditLog::query()->sole()->only(['action', 'actor_id', 'auditable_id']))
        ->toBe(['action' => AuditAction::MatchingConflictResolved->value, 'actor_id' => $staff->id, 'auditable_id' => $conflict->id]);
})->with([ConflictStatus::Resolved, ConflictStatus::Dismissed]);

it('refuses merchants, closed conflicts and an open resolution', function (string $case) {
    $conflict = MatchingConflict::factory()->create(['status' => ConflictStatus::Open]);
    $resolve = app(ResolveConflict::class);

    match ($case) {
        'merchant' => $resolve->handle($conflict, ConflictStatus::Resolved, MatchingActor::merchant(User::factory()->create(), 1), Scenario::at()),
        'closed' => $resolve->handle($resolve->handle($conflict, ConflictStatus::Dismissed, Scenario::staffActor(), Scenario::at()), ConflictStatus::Resolved, Scenario::staffActor(), Scenario::at()),
        'open resolution' => $resolve->handle($conflict, ConflictStatus::Open, Scenario::staffActor(), Scenario::at()),
    };
})->with(['merchant', 'closed', 'open resolution'])->throws(MatchDecisionNotAllowed::class);
