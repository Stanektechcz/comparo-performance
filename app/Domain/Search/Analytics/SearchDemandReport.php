<?php

namespace App\Domain\Search\Analytics;

use App\Models\SearchDemandDaily;
use DateTimeImmutable;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;

/**
 * Reads aggregated search demand under a k-anonymity threshold (A-24,
 * k = `minSessions`). A query is exposed only when BOTH hold in the window:
 *
 * - on at least one day, k DISTINCT sessions searched it (max(daily sessions) ≥ k), and
 * - the window total of daily sessions is ≥ k.
 *
 * Why per day (BACKLOG F-16): session hashes rotate daily by design, so
 * distinctness can only be established within one day — linking a browser
 * across days is exactly what the rotation prevents. Summing daily counts
 * alone let one browser searching on k different days pass as k people; the
 * per-day condition closes that. (The sum condition is implied by the per-day
 * one and kept explicit as the stated rule.) The reported `sessions` is still
 * the window sum of daily distinct sessions.
 */
final readonly class SearchDemandReport
{
    public const int MAX_DAYS = 400;

    public const int DEFAULT_LIMIT = 50;

    /**
     * @param  int  $days  the window ending with `$until` (inclusive), 1–400
     * @return list<array{query: string, query_hash: string, searches: int, zero_results: int, clicks: int, sessions: int}>
     */
    public function topQueries(string $market, int $days, int $minSessions = AnalyticsSettings::MIN_DEMAND_SESSIONS, ?DateTimeImmutable $until = null, int $limit = self::DEFAULT_LIMIT): array
    {
        if ($days < 1 || $days > self::MAX_DAYS || $minSessions < 1 || $limit < 1) {
            throw new InvalidArgumentException('Invalid demand window, session threshold or limit.');
        }

        $last = ($until ?? Date::now()->toImmutable())->format('Y-m-d');
        $first = (new DateTimeImmutable($last))->modify('-'.($days - 1).' days')->format('Y-m-d');

        $rows = SearchDemandDaily::query()
            ->where('market', strtoupper($market))
            ->whereDate('date', '>=', $first)
            ->whereDate('date', '<=', $last)
            ->groupBy('query_hash')
            ->selectRaw('query_hash, max(query_normalized) as query, sum(searches) as searches, sum(zero_results) as zero_results, sum(clicks) as clicks, sum(sessions) as sessions')
            ->havingRaw('max(sessions) >= ?', [$minSessions])
            ->havingRaw('sum(sessions) >= ?', [$minSessions])
            ->orderByDesc('searches')
            ->orderBy('query_hash')
            ->limit($limit)
            ->toBase()
            ->get();

        return array_values($rows->map(static fn (object $row): array => [
            'query' => (string) $row->query,
            'query_hash' => (string) $row->query_hash,
            'searches' => (int) $row->searches,
            'zero_results' => (int) $row->zero_results,
            'clicks' => (int) $row->clicks,
            'sessions' => (int) $row->sessions,
        ])->all());
    }
}
