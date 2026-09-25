<?php

namespace App\Domain\Search\Analytics;

use App\Domain\Search\SearchSource;
use App\Models\SearchClick;
use App\Models\SearchDemandDaily;
use App\Models\SearchQuery as SearchQueryRecord;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Builds `search_demand_daily` for one UTC day from non-bot search-page
 * rows, per market and redacted query:
 * - `searches`: first-page searches (paging through results is not a new search);
 * - `zero_results`: those that found nothing;
 * - `clicks`: accepted result clicks on any row of that query;
 * - `sessions`: distinct daily session hashes (rows without one do not count).
 *
 * Suggest rows are excluded: they are partial keystroke prefixes, not
 * finished queries. Re-running a day replaces that day's rows (idempotent).
 * Storage keeps every query; the k ≥ 3 sessions threshold is applied when
 * demand is read (SearchDemandReport).
 */
final class AggregateSearchDemand
{
    private const int CHUNK = 1000;

    /**
     * @return int the number of (market, query) rows written
     */
    public function run(DateTimeImmutable $day): int
    {
        [$from, $until] = self::bounds($day);
        $groups = [];

        $this->rows($from, $until)->each(static function (SearchQueryRecord $row) use (&$groups): void {
            $key = $row->market.'|'.$row->query_hash;
            $group = $groups[$key] ?? [
                'market' => $row->market,
                'query_hash' => $row->query_hash,
                'query_normalized' => $row->query_normalized,
                'searches' => 0,
                'zero_results' => 0,
                'clicks' => 0,
                'sessions' => [],
            ];

            if (! isset(($row->filters ?? [])['page'])) {
                $group['searches']++;
                $group['zero_results'] += $row->result_count === 0 ? 1 : 0;
            }

            if ($row->session_hash !== null) {
                $group['sessions'][$row->session_hash] = true;
            }

            $groups[$key] = $group;
        });

        foreach ($this->clicks($from, $until) as $key => $clicks) {
            if (isset($groups[$key])) {
                $groups[$key]['clicks'] = $clicks;
            }
        }

        $date = $from->format('Y-m-d');
        $now = now();
        $records = array_values(array_map(static fn (array $group): array => [
            'date' => $date,
            'market' => $group['market'],
            'query_hash' => $group['query_hash'],
            'query_normalized' => $group['query_normalized'],
            'searches' => $group['searches'],
            'zero_results' => $group['zero_results'],
            'clicks' => $group['clicks'],
            'sessions' => count($group['sessions']),
            'created_at' => $now,
            'updated_at' => $now,
        ], $groups));

        DB::transaction(static function () use ($date, $records): void {
            SearchDemandDaily::query()->whereDate('date', $date)->delete();

            foreach (array_chunk($records, self::CHUNK) as $chunk) {
                SearchDemandDaily::query()->insert($chunk);
            }
        });

        return count($records);
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable} [start of the UTC day, start of the next]
     */
    public static function bounds(DateTimeImmutable $day): array
    {
        $from = $day->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0);

        return [$from, $from->modify('+1 day')];
    }

    /**
     * @return LazyCollection<int, SearchQueryRecord>
     */
    private function rows(DateTimeImmutable $from, DateTimeImmutable $until): LazyCollection
    {
        return self::scope(SearchQueryRecord::query(), $from, $until)
            ->select(['search_id', 'market', 'query_hash', 'query_normalized', 'filters', 'result_count', 'session_hash'])
            ->lazyById(self::CHUNK, 'search_id');
    }

    /**
     * @return array<string, int> "market|query_hash" => clicks
     */
    private function clicks(DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        $rows = SearchClick::query()
            ->join('search_queries', 'search_queries.search_id', '=', 'search_clicks.search_id')
            ->whereIn('search_clicks.search_id', self::scope(SearchQueryRecord::query(), $from, $until)->select('search_id'))
            ->groupBy('search_queries.market', 'search_queries.query_hash')
            ->selectRaw('search_queries.market as market, search_queries.query_hash as query_hash, count(*) as clicks')
            ->toBase()
            ->get();

        $clicks = [];
        foreach ($rows as $row) {
            $clicks[$row->market.'|'.$row->query_hash] = (int) $row->clicks;
        }

        return $clicks;
    }

    /**
     * @template TBuilder of Builder<SearchQueryRecord>
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    private static function scope(Builder $query, DateTimeImmutable $from, DateTimeImmutable $until): Builder
    {
        return $query
            ->where('occurred_at', '>=', $from)
            ->where('occurred_at', '<', $until)
            ->where('is_bot', false)
            ->where('source', SearchSource::Page->value);
    }
}
