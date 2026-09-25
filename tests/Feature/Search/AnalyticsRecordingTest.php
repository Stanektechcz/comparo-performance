<?php

use App\Domain\Search\Analytics\SessionHasher;
use App\Domain\Search\Analytics\StoreSearchQuery;
use App\Domain\Search\Events\SearchPerformed;
use App\Domain\Search\Events\ZeroResultSearchRecorded;
use App\Models\SearchQuery as SearchQueryRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Search\Support\SearchScenario;
use Tests\Support\CatalogScenario;

/**
 * RecordSearch (docs/architecture/phase-3-search.md §6): what a search page
 * view writes to search_queries, and when it writes nothing.
 */
const RECORDING_BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';

function recordingCatalog(): int
{
    SearchScenario::useDatabaseEngine();
    $scenario = CatalogScenario::create();
    $product = $scenario->product(['name' => 'Kreatin Monohydrat', 'slug' => 'kreatin-monohydrat']);
    $scenario->allow($product);
    $scenario->offer($product, $scenario->merchant(['DE' => 390]), 1990);
    SearchScenario::reindexAll(Date::now()->toImmutable());

    return $product->id;
}

afterEach(fn () => Carbon::setTestNow());

it('stores a redacted, pseudonymous row with allow-listed filters and the displayed refs', function () {
    $productId = recordingCatalog();
    $session = Str::random(40);

    $searchId = $this->withCookie((string) config('session.cookie'), $session)
        ->withHeader('User-Agent', RECORDING_BROWSER)
        ->get(route('search', ['q' => ' Kreatin  jane@example.com ', 'sort' => 'price_asc', 'in_stock' => 1]))
        ->assertOk()
        ->viewData('page')['props']['searchId'];

    $record = SearchQueryRecord::query()->sole();

    expect($record->search_id)->toBe($searchId)
        ->and($record->market)->toBe('DE')
        ->and($record->locale)->toBe('de-DE')
        ->and($record->source->value)->toBe('page')
        ->and($record->query_normalized)->toBe('kreatin [email]')
        ->and($record->query_hash)->toBe(hash('sha256', 'kreatin [email]'))
        ->and($record->filters)->toBe(['sort' => 'price_asc', 'in_stock' => true])
        ->and($record->result_refs)->toBe([['type' => 'product', 'id' => $productId]])
        ->and($record->result_count)->toBe(1)
        ->and($record->session_hash)->toBe(app(SessionHasher::class)->hash($session, $record->occurred_at))
        ->and($record->is_bot)->toBeFalse();
});

it('never has or writes an IP address, a user id, a user agent or a raw session id', function () {
    recordingCatalog();
    $session = Str::random(40);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withCookie((string) config('session.cookie'), $session)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
        ->withHeader('User-Agent', RECORDING_BROWSER)
        ->get(route('search', ['q' => 'kreatin']))
        ->assertOk();

    $columns = [...Schema::getColumnListing('search_queries'), ...Schema::getColumnListing('search_clicks'), ...Schema::getColumnListing('search_demand_daily')];
    $stored = json_encode(SearchQueryRecord::query()->sole()->getAttributes());

    expect(preg_grep('/ip|user|agent|email|session_id/i', $columns))->toBe([])
        ->and($stored)->not->toContain('203.0.113.77')
        ->not->toContain('Chrome/129')
        ->not->toContain($session)
        ->not->toContain($user->email)
        ->not->toContain('"'.$user->id.'"');
});

it('rotates the session hash by UTC day for the same browser session', function () {
    recordingCatalog();
    $session = Str::random(40);
    $search = fn () => $this->withCookie((string) config('session.cookie'), $session)->withHeader('User-Agent', RECORDING_BROWSER)->get(route('search', ['q' => 'kreatin']))->assertOk();

    Carbon::setTestNow('2026-09-25 08:00:00');
    $search();
    Carbon::setTestNow('2026-09-25 22:00:00');
    $search();
    Carbon::setTestNow('2026-09-26 08:00:00');
    $search();

    $hashes = SearchQueryRecord::query()->orderBy('occurred_at')->pluck('session_hash')->all();

    expect($hashes)->toHaveCount(3)
        ->and($hashes[0])->toBe($hashes[1])
        ->and($hashes[2])->not->toBe($hashes[0]);
});

it('flags bots conservatively and still stores their rows', function (string $agent, bool $isBot) {
    recordingCatalog();

    $this->withHeader('User-Agent', $agent)->get(route('search', ['q' => 'kreatin']))->assertOk();

    expect(SearchQueryRecord::query()->sole()->is_bot)->toBe($isBot);
})->with([
    'crawler' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', true],
    'script' => ['python-requests/2.31', true],
    'browser' => [RECORDING_BROWSER, false],
]);

it('skips Inertia and browser prefetch requests', function (string $header) {
    recordingCatalog();

    $props = $this->withHeader($header, 'prefetch')->get(route('search', ['q' => 'kreatin']))->assertOk()->viewData('page')['props'];

    expect($props['searchId'])->toBeNull()
        ->and(SearchQueryRecord::query()->count())->toBe(0);
})->with(['Inertia (Purpose)' => ['Purpose'], 'browser (Sec-Purpose)' => ['Sec-Purpose']]);

it('queues the write on the analytics queue so the search never waits for it', function () {
    recordingCatalog();
    Queue::fake();

    $searchId = $this->get(route('search', ['q' => 'kreatin']))->assertOk()->viewData('page')['props']['searchId'];

    Queue::assertPushedOn('analytics', StoreSearchQuery::class, fn (StoreSearchQuery $job): bool => $job->searchId === $searchId);
    expect(SearchQueryRecord::query()->count())->toBe(0);
});

it('dispatches SearchPerformed and, without results, ZeroResultSearchRecorded', function () {
    recordingCatalog();
    Event::fake([SearchPerformed::class, ZeroResultSearchRecorded::class]);

    $this->get(route('search', ['q' => 'kreatin']))->assertOk();
    Event::assertDispatched(SearchPerformed::class, fn (SearchPerformed $event): bool => $event->resultCount === 1 && $event->market === 'DE');
    Event::assertNotDispatched(ZeroResultSearchRecorded::class);

    $this->get(route('search', ['q' => 'qqxqzz']))->assertOk();
    Event::assertDispatched(ZeroResultSearchRecorded::class, fn (ZeroResultSearchRecorded $event): bool => $event->queryHash === hash('sha256', 'qqxqzz'));
});

it('does not record texts under the searchable minimum', function () {
    recordingCatalog();

    $this->get(route('search', ['q' => 'k']))->assertOk();

    expect(SearchQueryRecord::query()->count())->toBe(0);
});

it('writes each prepared row once even when the job is retried', function () {
    $job = new StoreSearchQuery(
        searchId: strtolower((string) Str::ulid()),
        occurredAt: Date::now('UTC')->format(StoreSearchQuery::DATE_FORMAT),
        market: 'DE',
        locale: 'de-DE',
        source: 'page',
        queryNormalized: 'whey',
        queryHash: hash('sha256', 'whey'),
        filters: null,
        resultCount: 0,
        resultRefs: [],
        sessionHash: null,
        isBot: false,
    );

    $job->handle();
    $job->handle();

    expect(SearchQueryRecord::query()->count())->toBe(1);
});
