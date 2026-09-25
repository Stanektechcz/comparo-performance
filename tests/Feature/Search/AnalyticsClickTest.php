<?php

use App\Domain\Search\Analytics\SessionHasher;
use App\Domain\Search\Events\SearchResultClicked;
use App\Models\SearchClick;
use App\Models\SearchQuery as SearchQueryRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Feature\Search\Support\SearchScenario;
use Tests\Support\CatalogScenario;

/**
 * POST /search/clicks (docs/architecture/phase-3-search.md §6): a click is
 * attributed only to a recent search of the same session that listed that
 * entity at that position, once per (search, entity); the endpoint always
 * answers 204. JSON requests carry cookies only `withCredentials()`.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-25 12:00:00');
    $this->session = Str::random(40);
    $this->search = SearchQueryRecord::factory()->create([
        'occurred_at' => now()->subMinutes(5),
        'result_refs' => [['type' => 'product', 'id' => 11], ['type' => 'shop', 'id' => 7], ['type' => 'product', 'id' => 12]],
        'session_hash' => app(SessionHasher::class)->hash($this->session, now()->subMinutes(5)),
    ]);
});

afterEach(fn () => Carbon::setTestNow());

/**
 * @param  array<string, mixed>  $overrides
 */
function postSearchClick(mixed $test, array $overrides = [], ?string $session = null): void
{
    $test->withCredentials()
        ->withCookie((string) config('session.cookie'), $session ?? $test->session)
        ->postJson(route('search.clicks'), [
            'search_id' => $test->search->search_id,
            'entity_type' => 'product',
            'entity_id' => 11,
            'position' => 1,
            ...$overrides,
        ])
        ->assertNoContent();
}

it('records a click of the same session on the listed entity and position', function () {
    Event::fake([SearchResultClicked::class]);

    postSearchClick($this);

    $click = SearchClick::query()->sole();
    expect($click->search_id)->toBe($this->search->search_id)
        ->and($click->entity_type->value)->toBe('product')
        ->and($click->entity_id)->toBe(11)
        ->and($click->position)->toBe(1);
    Event::assertDispatched(SearchResultClicked::class, fn (SearchResultClicked $event): bool => $event->entityId === 11 && $event->position === 1);
});

it('stores shop clicks as merchant entities', function () {
    postSearchClick($this, ['entity_type' => 'shop', 'entity_id' => 7, 'position' => 2]);

    expect(SearchClick::query()->sole()->entity_type->value)->toBe('merchant');
});

it('deduplicates clicks per search and entity', function () {
    postSearchClick($this);
    postSearchClick($this);
    postSearchClick($this, ['entity_id' => 12, 'position' => 3]);

    expect(SearchClick::query()->count())->toBe(2);
});

it('ignores clicks that do not match the recorded search, always with 204', function (array $overrides) {
    Event::fake([SearchResultClicked::class]);

    postSearchClick($this, $overrides);

    expect(SearchClick::query()->count())->toBe(0);
    Event::assertNotDispatched(SearchResultClicked::class);
})->with([
    'wrong position' => [['position' => 2]],
    'entity not listed' => [['entity_id' => 99]],
    'wrong type at position' => [['entity_type' => 'brand']],
    'unknown search' => [['search_id' => '01j0000000000000000000000a']],
    'invalid search id' => [['search_id' => 'not-a-ulid']],
    'unknown entity type' => [['entity_type' => 'coupon']],
    'missing position' => [['position' => null]],
    'position zero' => [['position' => 0]],
]);

it('ignores clicks from another session', function () {
    postSearchClick($this, session: Str::random(40));

    expect(SearchClick::query()->count())->toBe(0);
});

it('ignores clicks more than 30 minutes after the search', function () {
    Carbon::setTestNow(now()->addMinutes(26));

    postSearchClick($this);

    expect(SearchClick::query()->count())->toBe(0);
});

it('verifies the session against the search day even after midnight UTC', function () {
    Carbon::setTestNow('2026-09-25 23:58:00');
    $search = SearchQueryRecord::factory()->create([
        'occurred_at' => now(),
        'result_refs' => [['type' => 'product', 'id' => 11]],
        'session_hash' => app(SessionHasher::class)->hash($this->session, now()),
    ]);
    Carbon::setTestNow('2026-09-26 00:03:00');

    postSearchClick($this, ['search_id' => $search->search_id]);

    expect(SearchClick::query()->where('search_id', $search->search_id)->count())->toBe(1);
});

it('ignores searches recorded without a session (suggest API)', function () {
    $this->search->update(['session_hash' => null]);

    postSearchClick($this);

    expect(SearchClick::query()->count())->toBe(0);
});

it('throttles the beacon per IP', function () {
    foreach (range(1, 60) as $attempt) {
        $this->postJson(route('search.clicks'), [])->assertNoContent();
    }

    $this->postJson(route('search.clicks'), [])->assertTooManyRequests();
});

it('attributes a click on a result of a real search page view', function () {
    Carbon::setTestNow();
    SearchScenario::useDatabaseEngine();
    $scenario = CatalogScenario::create();
    $product = $scenario->product(['name' => 'Kreatin Monohydrat']);
    $scenario->allow($product);
    $scenario->offer($product, $scenario->merchant(['DE' => 390]), 1990);
    SearchScenario::reindexAll(Date::now()->toImmutable());

    $props = $this->withCookie((string) config('session.cookie'), $this->session)
        ->get(route('search', ['q' => 'kreatin']))
        ->assertOk()
        ->viewData('page')['props'];

    $this->withCredentials()
        ->withCookie((string) config('session.cookie'), $this->session)
        ->postJson(route('search.clicks'), ['search_id' => $props['searchId'], 'entity_type' => 'product', 'entity_id' => $product->id, 'position' => 1])
        ->assertNoContent();

    expect(SearchClick::query()->where('search_id', $props['searchId'])->sole()->entity_id)->toBe($product->id);
});
