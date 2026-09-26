<?php

use App\Domain\Search\Analytics\SearchDemandReport;
use App\Models\SearchDemandDaily;

/*
 * BACKLOG F-16: session hashes rotate daily, so distinct sessions can only be
 * counted within one day. A query is exposed only when on at least one day of
 * the window k distinct sessions searched it (and the window sum is ≥ k);
 * one browser searching on k different days is never enough.
 */

/**
 * @param  list<int>  $dailySessions  sessions per consecutive day ending 2026-09-24
 */
function demandDays(string $query, array $dailySessions, string $market = 'DE'): void
{
    $day = new DateTimeImmutable('2026-09-24');

    foreach (array_reverse($dailySessions) as $offset => $sessions) {
        SearchDemandDaily::factory()->create([
            'date' => $day->modify("-{$offset} days")->format('Y-m-d'),
            'market' => $market,
            'query_hash' => hash('sha256', $query),
            'query_normalized' => $query,
            'searches' => $sessions * 2,
            'zero_results' => 0,
            'clicks' => 0,
            'sessions' => $sessions,
        ]);
    }
}

function exposedQueries(int $minSessions = 3, int $days = 7): array
{
    return array_column(
        app(SearchDemandReport::class)->topQueries('DE', $days, $minSessions, new DateTimeImmutable('2026-09-24')),
        'query',
    );
}

it('does not expose a query whose sessions only reach k when summed across days', function () {
    demandDays('one browser daily', [1, 1, 1, 1, 1]);
    demandDays('two a day', [2, 2, 2]);

    expect(exposedQueries())->toBe([]);
});

it('exposes a query that reached k distinct sessions on one day, reporting the window totals', function () {
    demandDays('creatine', [1, 3, 1]);

    expect(app(SearchDemandReport::class)->topQueries('DE', 7, 3, new DateTimeImmutable('2026-09-24')))->toBe([
        ['query' => 'creatine', 'query_hash' => hash('sha256', 'creatine'), 'searches' => 10, 'zero_results' => 0, 'clicks' => 0, 'sessions' => 5],
    ]);
});

it('only counts the qualifying day when it lies inside the window', function () {
    demandDays('whey', [4, 0, 1, 1, 1, 1, 1, 1, 1]);

    expect(exposedQueries(days: 7))->toBe([])
        ->and(exposedQueries(days: 9))->toBe(['whey']);
});

it('applies the threshold per market', function () {
    demandDays('kreatin', [5], 'CZ');
    demandDays('kreatin', [2]);

    expect(exposedQueries())->toBe([]);
});
