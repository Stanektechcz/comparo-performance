<?php

use App\Domain\Search\Analytics\AggregateSearchDemand;
use App\Domain\Search\Analytics\SearchDemandReport;
use App\Domain\Search\SearchEntityType;
use App\Models\SearchClick;
use App\Models\SearchDemandDaily;
use App\Models\SearchQuery as SearchQueryRecord;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

/**
 * Demand aggregation, the k ≥ 3 sessions read threshold and retention
 * (docs/architecture/phase-3-search.md §6, A-24).
 */
afterEach(fn () => Carbon::setTestNow());

/**
 * @param  array<string, mixed>  $attributes
 */
function demandSearch(string $query, string $at, ?string $session, array $attributes = []): SearchQueryRecord
{
    return SearchQueryRecord::factory()->forQuery($query)->create([
        'occurred_at' => $at,
        'session_hash' => $session === null ? null : hash('sha256', $session),
        ...$attributes,
    ]);
}

function demandClick(SearchQueryRecord $search, int $entityId): void
{
    SearchClick::factory()->create([
        'search_id' => $search->search_id,
        'entity_type' => SearchEntityType::Product,
        'entity_id' => $entityId,
        'position' => 1,
        'clicked_at' => $search->occurred_at,
    ]);
}

it('aggregates one UTC day of human search-page queries per market and query', function () {
    $first = demandSearch('creatine', '2026-09-24 00:10:00', 's1');
    demandSearch('creatine', '2026-09-24 09:00:00', 's1', ['filters' => ['page' => 2]]);
    $third = demandSearch('creatine', '2026-09-24 12:00:00', 's2', ['result_count' => 0, 'result_refs' => []]);
    demandSearch('creatine', '2026-09-24 23:59:59', 's3');
    demandSearch('creatine', '2026-09-24 13:00:00', null);
    demandSearch('creatine', '2026-09-24 14:00:00', 'bot', ['is_bot' => true]);
    demandSearch('creatine', '2026-09-24 15:00:00', 's4', ['source' => 'suggest']);
    demandSearch('creatine', '2026-09-25 00:00:00', 's5');
    demandSearch('creatine', '2026-09-24 10:00:00', 's6', ['market' => 'CZ']);
    demandClick($first, 1);
    demandClick($first, 2);
    demandClick($third, 1);

    $rows = app(AggregateSearchDemand::class)->run(new DateTimeImmutable('2026-09-24 18:00:00', new DateTimeZone('UTC')));
    $de = SearchDemandDaily::query()->where('market', 'DE')->sole();

    expect($rows)->toBe(2)
        ->and($de->date->format('Y-m-d'))->toBe('2026-09-24')
        ->and($de->query_normalized)->toBe('creatine')
        ->and($de->query_hash)->toBe(hash('sha256', 'creatine'))
        ->and($de->searches)->toBe(4)
        ->and($de->zero_results)->toBe(1)
        ->and($de->clicks)->toBe(3)
        ->and($de->sessions)->toBe(3)
        ->and(SearchDemandDaily::query()->where('market', 'CZ')->sole()->searches)->toBe(1);
});

it('replaces a day when it is aggregated again', function () {
    demandSearch('whey', '2026-09-24 10:00:00', 's1');
    $day = new DateTimeImmutable('2026-09-24', new DateTimeZone('UTC'));

    app(AggregateSearchDemand::class)->run($day);
    demandSearch('whey', '2026-09-24 11:00:00', 's2');
    app(AggregateSearchDemand::class)->run($day);

    expect(SearchDemandDaily::query()->sole()->searches)->toBe(2);
});

it('exposes a query only when at least 3 sessions searched it', function () {
    foreach (['2026-09-20', '2026-09-22', '2026-09-24'] as $index => $date) {
        SearchDemandDaily::factory()->create(['date' => $date, 'market' => 'DE', 'query_hash' => hash('sha256', 'creatine'), 'query_normalized' => 'creatine', 'searches' => 5, 'zero_results' => 1, 'clicks' => 2, 'sessions' => 1]);
    }
    SearchDemandDaily::factory()->create(['date' => '2026-09-24', 'market' => 'DE', 'query_hash' => hash('sha256', 'rare'), 'query_normalized' => 'rare', 'searches' => 9, 'zero_results' => 9, 'clicks' => 0, 'sessions' => 2]);
    SearchDemandDaily::factory()->create(['date' => '2026-09-24', 'market' => 'CZ', 'query_hash' => hash('sha256', 'kreatin'), 'query_normalized' => 'kreatin', 'searches' => 9, 'zero_results' => 0, 'clicks' => 0, 'sessions' => 5]);
    SearchDemandDaily::factory()->create(['date' => '2026-08-01', 'market' => 'DE', 'query_hash' => hash('sha256', 'old'), 'query_normalized' => 'old', 'searches' => 50, 'zero_results' => 0, 'clicks' => 0, 'sessions' => 50]);

    $report = app(SearchDemandReport::class);
    $until = new DateTimeImmutable('2026-09-24');

    expect($report->topQueries('DE', 7, until: $until))->toBe([
        ['query' => 'creatine', 'query_hash' => hash('sha256', 'creatine'), 'searches' => 15, 'zero_results' => 3, 'clicks' => 6, 'sessions' => 3],
    ])
        ->and(array_column($report->topQueries('DE', 7, 2, $until), 'query'))->toBe(['creatine', 'rare'])
        ->and(array_column($report->topQueries('de', 90, until: $until), 'query'))->toBe(['old', 'creatine']);
});

it('aggregates yesterday by default and validates --date', function () {
    Carbon::setTestNow('2026-09-25 00:20:00');
    demandSearch('whey', '2026-09-24 10:00:00', 's1');

    $this->artisan('comparo:search:aggregate-demand')->assertSuccessful();
    expect(SearchDemandDaily::query()->sole()->date->format('Y-m-d'))->toBe('2026-09-24');

    $this->artisan('comparo:search:aggregate-demand', ['--date' => '2026-09-24'])->assertSuccessful();
    $this->artisan('comparo:search:aggregate-demand', ['--date' => '24.09.2026'])->assertExitCode(2);
    $this->artisan('comparo:search:aggregate-demand', ['--date' => '2026-02-30'])->assertExitCode(2);
});

it('nulls session hashes after 90 days and deletes raw rows after 13 months', function () {
    Carbon::setTestNow('2026-09-25 03:00:00');
    $recent = demandSearch('recent', '2026-09-01 10:00:00', 's1');
    $oldSession = demandSearch('old session', now()->subDays(91)->toDateTimeString(), 's2');
    $expired = demandSearch('expired', now()->subMonths(13)->subDay()->toDateTimeString(), 's3');
    demandClick($expired, 1);
    demandClick($recent, 1);

    $this->artisan('comparo:search:prune-analytics')->assertSuccessful();

    expect($recent->fresh()?->session_hash)->not->toBeNull()
        ->and($oldSession->fresh()?->session_hash)->toBeNull()
        ->and($oldSession->fresh()?->query_normalized)->toBe('old session')
        ->and(SearchQueryRecord::query()->find($expired->search_id))->toBeNull()
        ->and(SearchClick::query()->pluck('search_id')->all())->toBe([$recent->search_id]);
});

it('reads retention periods from config', function () {
    Carbon::setTestNow('2026-09-25 03:00:00');
    config(['comparo.search.analytics.session_hash_days' => 10]);
    $search = demandSearch('whey', now()->subDays(11)->toDateTimeString(), 's1');

    $this->artisan('comparo:search:prune-analytics')->assertSuccessful();

    expect($search->fresh()?->session_hash)->toBeNull();
});

it('schedules aggregation and pruning daily', function () {
    $commands = collect(app(Schedule::class)->events())->map(fn ($event): string => (string) $event->command)->implode("\n");

    expect($commands)->toContain('comparo:search:aggregate-demand')
        ->toContain('comparo:search:prune-analytics');
});
