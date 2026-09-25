<?php

namespace App\Domain\Feeds\Actions;

use App\Domain\Feeds\Exceptions\InvalidFeedTransition;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\Lifecycle\FeedActorKind;
use App\Domain\Feeds\Lifecycle\FeedRunTransitions;
use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Audit\AuditLogger;
use App\Models\FeedRun;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cancels a queued or running run on behalf of a merchant member or staff.
 * Only queued…matching are cancellable: once publishing, part of the run may
 * already be live. Jobs of the cancelled run lose their next compare-and-swap
 * and exit quietly. Audited as `feed_run.cancelled`.
 */
final class CancelFeedRun
{
    public function __construct(
        private readonly FeedRunTransitions $transitions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws InvalidFeedTransition when the run is publishing or already finished
     */
    public function handle(FeedRun $run, FeedActor $actor): FeedRun
    {
        if ($actor->kind === FeedActorKind::System) {
            throw new InvalidArgumentException('Runs are cancelled by merchant members or staff.');
        }

        return DB::transaction(function () use ($run, $actor): FeedRun {
            $current = FeedRun::query()->findOrFail($run->id);
            $cancellable = array_values(array_filter(FeedRunStatus::active(), static fn (FeedRunStatus $status): bool => $status->isCancellable()));

            if (! $current->status->isCancellable()
                || ! $this->transitions->advanceFromAny($current->id, $cancellable, FeedRunStatus::Cancelled)) {
                throw InvalidFeedTransition::forRun($current->refresh()->status, FeedRunStatus::Cancelled);
            }

            $this->audit->record(
                AuditAction::FeedRunCancelled,
                $actor->audit,
                $current,
                ['status' => $current->status->value],
                ['status' => FeedRunStatus::Cancelled->value],
            );

            return $current->refresh();
        });
    }
}
