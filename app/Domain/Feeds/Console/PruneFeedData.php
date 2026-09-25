<?php

namespace App\Domain\Feeds\Console;

use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\Pipeline\FeedStorage;
use App\Models\FeedItem;
use App\Models\FeedRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Retention (§5, §8):
 * - payload files older than `payload_retention_days` are deleted and the run
 *   marked `payload_purged_at`;
 * - staged feed_items of finished runs older than `item_retention_days` are
 *   deleted, except those of each source's latest
 *   `item_retention_keep_successful_runs` successful runs. feed_errors survive
 *   (their item reference is nulled by the foreign key).
 * Chunked; runs still in progress are never touched.
 */
final class PruneFeedData
{
    private const int CHUNK = 200;

    private const int DELETE_BATCH = 1000;

    public function __construct(private readonly FeedStorage $storage) {}

    /**
     * @return array{payloads: int, items: int}
     */
    public function run(CarbonImmutable $now): array
    {
        return [
            'payloads' => $this->prunePayloads($now->subDays(max(1, (int) config('comparo.feeds.payload_retention_days', 30))), $now),
            'items' => $this->pruneItems($now->subDays(max(1, (int) config('comparo.feeds.item_retention_days', 7)))),
        ];
    }

    private function prunePayloads(CarbonImmutable $cutoff, CarbonImmutable $now): int
    {
        $purged = 0;

        FeedRun::query()
            ->whereNotNull('payload_path')
            ->whereNull('payload_purged_at')
            ->where('created_at', '<', $cutoff)
            ->whereNotIn('status', FeedRunStatus::activeValues())
            ->select(['id', 'payload_path'])
            ->chunkById(self::CHUNK, function (Collection $runs) use ($now, &$purged): void {
                foreach ($runs as $run) {
                    $this->storage->delete((string) $run->payload_path);
                }

                FeedRun::query()->whereKey($runs->modelKeys())->toBase()->update(['payload_purged_at' => $now]);
                $purged += $runs->count();
            });

        return $purged;
    }

    private function pruneItems(CarbonImmutable $cutoff): int
    {
        $keep = max(0, (int) config('comparo.feeds.item_retention_keep_successful_runs', 2));
        $deleted = 0;
        $sourceIds = FeedRun::query()
            ->where('created_at', '<', $cutoff)
            ->whereNotIn('status', FeedRunStatus::activeValues())
            ->whereHas('items')
            ->distinct()
            ->pluck('feed_source_id');

        foreach ($sourceIds as $sourceId) {
            $kept = $keep === 0 ? [] : FeedRun::query()
                ->where('feed_source_id', $sourceId)
                ->successful()
                ->orderByDesc('id')
                ->limit($keep)
                ->pluck('id')
                ->all();

            $runIds = array_values(FeedRun::query()
                ->where('feed_source_id', $sourceId)
                ->where('created_at', '<', $cutoff)
                ->whereNotIn('status', FeedRunStatus::activeValues())
                ->whereNotIn('id', $kept)
                ->pluck('id')
                ->map(static fn (mixed $value): int => (int) $value)
                ->all());

            $deleted += $this->deleteItemsOf($runIds);
        }

        return $deleted;
    }

    /**
     * @param  list<int>  $runIds
     */
    private function deleteItemsOf(array $runIds): int
    {
        $deleted = 0;

        foreach (array_chunk($runIds, self::CHUNK) as $chunk) {
            do {
                $itemIds = FeedItem::query()->whereIn('feed_run_id', $chunk)->limit(self::DELETE_BATCH)->pluck('id')->all();
                $deleted += $itemIds === [] ? 0 : FeedItem::query()->whereKey($itemIds)->delete();
            } while ($itemIds !== []);
        }

        return $deleted;
    }
}
