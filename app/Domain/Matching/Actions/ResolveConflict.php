<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\ConflictStatus;
use App\Domain\Matching\Exceptions\MatchDecisionNotAllowed;
use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Audit\AuditActor;
use App\Domain\Platform\Audit\AuditLogger;
use App\Models\MatchingConflict;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * Staff close an open matching conflict as `resolved` (optionally with the
 * chosen value) or `dismissed`, with a note; audited as
 * `matching.conflict_resolved`. Closing a compliance-hold conflict only
 * clears the queue item: held listings stay held until a feed run's
 * compliance check releases them ({@see MatchListing}) or staff relink them.
 */
final class ResolveConflict
{
    private const int VALUE_MAX = 255;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws MatchDecisionNotAllowed
     */
    public function handle(
        MatchingConflict $conflict,
        ConflictStatus $resolution,
        MatchingActor $actor,
        DateTimeImmutable $resolvedAt,
        ?string $note = null,
        ?string $resolvedValue = null,
    ): MatchingConflict {
        $actor->assertStaff('resolve matching conflicts');

        if ($resolution->isOpen()) {
            throw MatchDecisionNotAllowed::invalidResolution($resolution->value);
        }

        return DB::transaction(function () use ($conflict, $resolution, $actor, $resolvedAt, $note, $resolvedValue): MatchingConflict {
            $locked = MatchingConflict::query()->lockForUpdate()->findOrFail($conflict->id);

            if (! $locked->status->isOpen()) {
                throw MatchDecisionNotAllowed::conflictNotOpen($locked->id);
            }

            $before = ['status' => $locked->status->value, 'resolved_value' => $locked->resolved_value];

            $locked->fill([
                'status' => $resolution,
                'resolved_value' => $resolvedValue === null ? null : mb_substr($resolvedValue, 0, self::VALUE_MAX),
                'resolved_by_user_id' => $actor->user->id,
                'resolved_at' => $resolvedAt->setTimezone(new DateTimeZone('UTC')),
                'resolution_note' => $note,
            ])->save();

            $this->audit->record(AuditAction::MatchingConflictResolved, AuditActor::user($actor->user), $locked, $before, [
                'status' => $resolution->value,
                'resolved_value' => $locked->resolved_value,
            ]);

            return $locked;
        });
    }
}
