<?php

namespace App\Domain\Feeds\Console;

use App\Domain\Feeds\Actions\StartFeedRun;
use App\Domain\Feeds\Exceptions\FeedRunAlreadyActive;
use App\Domain\Feeds\Exceptions\FeedRunNotAllowed;
use App\Domain\Feeds\FeedRunTrigger;
use App\Domain\Feeds\FeedSourceStatus;
use App\Domain\Feeds\FeedTransport;
use App\Models\FeedSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Starts the scheduled run of every due source: active URL feeds with an
 * interval whose next_run_at has passed. The idempotency key
 * `schedule:{source}:{slot}` (slot = floor(now / interval)) makes repeated
 * ticks within one interval start at most one run per source.
 */
final class ScheduleDueFeeds
{
    private const int CHUNK = 100;

    public function __construct(private readonly StartFeedRun $startFeedRun) {}

    /**
     * @return array{started: int, skipped: int}
     */
    public function run(CarbonImmutable $now): array
    {
        $counts = ['started' => 0, 'skipped' => 0];

        FeedSource::query()
            ->where('status', FeedSourceStatus::Active->value)
            ->where('transport', FeedTransport::Url->value)
            ->whereNotNull('interval_minutes')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->chunkById(self::CHUNK, function (Collection $sources) use ($now, &$counts): void {
                foreach ($sources as $source) {
                    $counts[$this->start($source, $now) ? 'started' : 'skipped']++;
                }
            });

        return $counts;
    }

    public static function idempotencyKey(FeedSource $source, CarbonImmutable $now): string
    {
        $slot = intdiv($now->getTimestamp(), max(1, (int) $source->interval_minutes) * 60);

        return 'schedule:'.$source->id.':'.$slot;
    }

    private function start(FeedSource $source, CarbonImmutable $now): bool
    {
        try {
            $run = $this->startFeedRun->handle($source, FeedRunTrigger::Schedule, idempotencyKey: self::idempotencyKey($source, $now));

            return $run->wasRecentlyCreated;
        } catch (FeedRunAlreadyActive|FeedRunNotAllowed) {
            return false;
        } catch (Throwable $exception) {
            // One broken source must not stop the others; the run itself records its failure.
            Log::warning('Scheduled feed run could not be started.', ['feed_source_id' => $source->id, 'exception' => $exception::class]);

            return false;
        }
    }
}
