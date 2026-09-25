<?php

namespace App\Domain\Feeds\Actions;

use App\Domain\Feeds\Exceptions\InvalidFeedTransition;
use App\Domain\Feeds\FeedSourceStatus;
use App\Domain\Feeds\Lifecycle\FeedSourceLifecycle;
use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Audit\AuditLogger;
use App\Models\FeedSource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Moves a feed source through its lifecycle ({@see FeedSourceLifecycle}):
 * merchants pause/resume, staff disable/re-enable, the pipeline activates or
 * errors a source after run outcomes. Audited as `feed_source.status_changed`
 * with the reason.
 *
 * A source leaving `active` is unscheduled (next_run_at = null); a resumed
 * source is due immediately.
 */
final class ChangeFeedSourceStatus
{
    public const int MAX_REASON_LENGTH = 64;

    public function __construct(
        private readonly FeedSourceLifecycle $lifecycle,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws InvalidFeedTransition
     */
    public function handle(FeedSource $source, FeedSourceStatus $to, FeedActor $actor, ?string $reason = null): FeedSource
    {
        return DB::transaction(function () use ($source, $to, $actor, $reason): FeedSource {
            $locked = FeedSource::query()->lockForUpdate()->findOrFail($source->id);

            return $this->transitionLocked($locked, $to, $actor, $reason);
        });
    }

    /**
     * For callers that already hold the source row lock inside their own transaction.
     *
     * @throws InvalidFeedTransition
     */
    public function transitionLocked(FeedSource $locked, FeedSourceStatus $to, FeedActor $actor, ?string $reason = null): FeedSource
    {
        $from = $locked->status;
        $this->lifecycle->assertTransition($from, $to, $actor->kind);
        $reason = $reason === null ? null : mb_substr(trim($reason), 0, self::MAX_REASON_LENGTH);

        $locked->fill([
            'status' => $to,
            'status_reason' => $reason === '' ? null : $reason,
            'next_run_at' => $to === FeedSourceStatus::Active && $from === FeedSourceStatus::Paused ? Date::now() : null,
        ])->save();

        $this->audit->record(
            AuditAction::FeedSourceStatusChanged,
            $actor->audit,
            $locked,
            ['status' => $from->value],
            ['status' => $to->value, 'reason' => $locked->status_reason],
        );

        return $locked;
    }
}
