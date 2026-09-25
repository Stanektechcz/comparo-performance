<?php

namespace App\Domain\Feeds\Lifecycle;

use App\Domain\Feeds\Exceptions\InvalidFeedTransition;
use App\Domain\Feeds\FeedRunStatus;
use App\Models\FeedRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * Compare-and-swap writes of the feed run state machine:
 * `UPDATE feed_runs SET status = ?, <stage>_at = ? … WHERE id = ? AND status = ?`.
 *
 * Returns whether this caller won the transition. A queued job that loses the
 * race (double delivery, cancellation, reaper) must exit quietly. Invalid
 * transitions throw before touching the database.
 */
final class FeedRunTransitions
{
    public function __construct(private readonly FeedRunLifecycle $lifecycle) {}

    /**
     * @param  array<string, mixed>  $attributes  extra columns written with the transition
     *
     * @throws InvalidFeedTransition
     */
    public function advance(int $runId, FeedRunStatus $from, FeedRunStatus $to, array $attributes = []): bool
    {
        return $this->advanceFromAny($runId, [$from], $to, $attributes);
    }

    /**
     * Transition from whichever of `$from` the run is currently in.
     *
     * @param  list<FeedRunStatus>  $from
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidFeedTransition
     */
    public function advanceFromAny(int $runId, array $from, FeedRunStatus $to, array $attributes = []): bool
    {
        foreach ($from as $status) {
            $this->lifecycle->assertTransition($status, $to);
        }

        $now = Date::now()->toImmutable();

        foreach ($from as $status) {
            $values = [
                ...$this->stageColumns($status, $to, $now),
                ...$attributes,
                'status' => $to->value,
                'updated_at' => $now,
            ];

            $updated = FeedRun::query()
                ->whereKey($runId)
                ->where('status', $status->value)
                ->toBase()
                ->update($values);

            if ($updated === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * The moment a run enters a stage is the moment the previous one finished.
     *
     * @return array<string, CarbonImmutable>
     */
    private function stageColumns(FeedRunStatus $from, FeedRunStatus $to, CarbonImmutable $now): array
    {
        $finishedStage = match ($from) {
            FeedRunStatus::Fetching => 'fetched_at',
            FeedRunStatus::Parsing => 'parsed_at',
            FeedRunStatus::Normalizing => 'normalized_at',
            FeedRunStatus::Matching => 'matched_at',
            FeedRunStatus::Publishing => 'published_at',
            default => null,
        };

        $columns = $to->isTerminal() ? ['finished_at' => $now] : [];

        if ($to === FeedRunStatus::Fetching) {
            $columns['started_at'] = $now;
        }

        // Only a stage that finished normally gets its timestamp.
        if ($finishedStage !== null && ! in_array($to, [FeedRunStatus::Failed, FeedRunStatus::Cancelled], true)) {
            $columns[$finishedStage] = $now;
        }

        return $columns;
    }
}
