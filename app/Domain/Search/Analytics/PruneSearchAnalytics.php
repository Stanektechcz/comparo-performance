<?php

namespace App\Domain\Search\Analytics;

use App\Models\SearchClick;
use App\Models\SearchQuery as SearchQueryRecord;
use DateTimeImmutable;

/**
 * Enforces search-analytics retention (A-24, D-09 defaults):
 * `session_hash` is nulled on rows older than `sessionHashDays` (90), raw
 * `search_queries` rows and their `search_clicks` are deleted after
 * `rawRetentionMonths` (13). `search_demand_daily` holds aggregates only and
 * is not pruned here.
 */
final readonly class PruneSearchAnalytics
{
    private const int CHUNK = 1000;

    public function __construct(private AnalyticsSettings $settings) {}

    /**
     * @return array{sessions_nulled: int, searches_deleted: int, clicks_deleted: int}
     */
    public function run(DateTimeImmutable $now): array
    {
        $sessionCutoff = $now->modify("-{$this->settings->sessionHashDays} days");
        $rawCutoff = $now->modify("-{$this->settings->rawRetentionMonths} months");

        $sessionsNulled = SearchQueryRecord::query()
            ->where('occurred_at', '<', $sessionCutoff)
            ->whereNotNull('session_hash')
            ->update(['session_hash' => null]);

        $searchesDeleted = 0;
        $clicksDeleted = 0;

        do {
            $ids = SearchQueryRecord::query()
                ->where('occurred_at', '<', $rawCutoff)
                ->orderBy('search_id')
                ->limit(self::CHUNK)
                ->pluck('search_id')
                ->all();

            if ($ids === []) {
                break;
            }

            $clicksDeleted += SearchClick::query()->whereIn('search_id', $ids)->delete();
            $searchesDeleted += SearchQueryRecord::query()->whereKey($ids)->delete();
        } while (count($ids) === self::CHUNK);

        return ['sessions_nulled' => $sessionsNulled, 'searches_deleted' => $searchesDeleted, 'clicks_deleted' => $clicksDeleted];
    }
}
