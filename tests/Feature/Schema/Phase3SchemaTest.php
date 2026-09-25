<?php

use App\Domain\Search\SearchEntityType;
use App\Domain\Search\SearchSource;
use App\Domain\Search\SynonymSource;
use App\Domain\Search\SynonymStatus;
use App\Models\SearchClick;
use App\Models\SearchDemandDaily;
use App\Models\SearchDocument;
use App\Models\SearchIndexOutbox;
use App\Models\SearchQuery;
use App\Models\SearchSynonym;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Phase 3 search schema (docs/architecture/phase-3-search.md §2, §5, §6):
 * engine storage, synonyms seeded from the prototype, the index outbox and
 * privacy-by-design analytics. Written to run on SQLite and PostgreSQL.
 */

/**
 * Runs a write inside a savepoint and reports whether the database rejected
 * it (PostgreSQL aborts the whole test transaction otherwise).
 */
function phase3Rejects(Closure $write): bool
{
    try {
        DB::transaction($write);
    } catch (QueryException) {
        return true;
    }

    return false;
}

/**
 * Column lists of the unique indexes (and primary key) of a table.
 *
 * @return list<list<string>>
 */
function phase3UniqueColumns(string $table): array
{
    $uniques = array_values(array_map(
        static fn (array $index): array => array_values($index['columns']),
        array_filter(Schema::getIndexes($table), static fn (array $index): bool => $index['unique']),
    ));
    sort($uniques);

    return $uniques;
}

/**
 * @return list<list<string>>
 */
function phase3PlainIndexColumns(string $table): array
{
    return array_values(array_map(
        static fn (array $index): array => array_values($index['columns']),
        array_filter(Schema::getIndexes($table), static fn (array $index): bool => ! $index['unique']),
    ));
}

/**
 * @return list<string>
 */
function phase3Columns(string $table): array
{
    $columns = Schema::getColumnListing($table);
    sort($columns);

    return $columns;
}

it('creates the search tables with exactly the designed columns', function () {
    $expected = [
        'search_documents' => ['document_id', 'entity_type', 'id', 'index_name', 'payload', 'schema_version', 'searchable_text', 'updated_at'],
        'search_synonyms' => ['created_at', 'group_key', 'id', 'source', 'status', 'term', 'updated_at'],
        'search_index_outbox' => ['entity', 'entity_id', 'id', 'priority', 'queued_at'],
        'search_clicks' => ['clicked_at', 'entity_id', 'entity_type', 'id', 'position', 'search_id'],
        'search_demand_daily' => ['clicks', 'created_at', 'date', 'id', 'market', 'query_hash', 'query_normalized', 'searches', 'sessions', 'updated_at', 'zero_results'],
    ];

    foreach ($expected as $table => $columns) {
        expect(phase3Columns($table))->toBe($columns, "unexpected columns on {$table}");
    }
});

it('stores no ip address and no user id with a recorded search', function () {
    expect(phase3Columns('search_queries'))->toBe([
        'created_at', 'filters', 'is_bot', 'locale', 'market', 'occurred_at', 'query_hash', 'query_normalized',
        'result_count', 'result_refs', 'search_id', 'session_hash', 'source',
    ]);

    foreach (['search_queries', 'search_clicks', 'search_demand_daily'] as $table) {
        foreach (Schema::getColumnListing($table) as $column) {
            expect($column)->not->toContain('ip')->not->toContain('user');
        }
    }
});

it('declares the designed unique and lookup indexes', function () {
    expect(phase3UniqueColumns('search_documents'))->toContain(['index_name', 'document_id'])
        ->and(phase3PlainIndexColumns('search_documents'))->toContain(['index_name', 'entity_type'])
        ->and(phase3UniqueColumns('search_synonyms'))->toContain(['group_key', 'term'])
        ->and(phase3UniqueColumns('search_index_outbox'))->toContain(['entity', 'entity_id'])
        ->and(phase3PlainIndexColumns('search_index_outbox'))->toContain(['priority', 'queued_at'])
        ->and(phase3UniqueColumns('search_queries'))->toContain(['search_id'])
        ->and(phase3PlainIndexColumns('search_queries'))->toContain(['occurred_at'])
        ->toContain(['market', 'occurred_at'])
        ->toContain(['query_hash'])
        ->toContain(['session_hash'])
        ->and(phase3UniqueColumns('search_clicks'))->toContain(['search_id', 'entity_type', 'entity_id'])
        ->and(phase3UniqueColumns('search_demand_daily'))->toContain(['date', 'market', 'query_hash']);
});

it('seeds exactly the prototype synonym groups in prototype order', function () {
    $groups = [];
    foreach (SearchSynonym::query()->orderBy('id')->get() as $synonym) {
        $groups[$synonym->group_key][] = $synonym->term;

        expect($synonym->source)->toBe(SynonymSource::Prototype)
            ->and($synonym->status)->toBe(SynonymStatus::Active);
    }

    expect($groups)->toBe([
        'protein' => ['whey', 'isolate', 'casein', 'protein'],
        'creatine' => ['creatine', 'monohydrate', 'creapure', 'kreatin'],
        'preworkout' => ['pre-workout', 'preworkout', 'pump', 'stim'],
        'amino' => ['eaa', 'bcaa', 'amino', 'glutamine'],
        'sleep' => ['melatonin', 'sleep', 'zma', 'ashwagandha'],
    ])
        ->and(SearchSynonym::query()->active()->count())->toBe(20);
});

it('rejects duplicate rows on every natural key', function () {
    $document = SearchDocument::factory()->create();
    $search = SearchQuery::factory()->create();
    SearchClick::factory()->forSearch($search)->create(['entity_id' => 7]);
    $demand = SearchDemandDaily::factory()->create();
    SearchIndexOutbox::factory()->forEntity(SearchEntityType::Brand, 9)->create();

    expect(phase3Rejects(fn () => SearchDocument::factory()->create(['document_id' => $document->document_id])))->toBeTrue()
        ->and(SearchDocument::factory()->inIndex('products_tmp')->create(['document_id' => $document->document_id])->exists)->toBeTrue()
        ->and(phase3Rejects(fn () => SearchSynonym::factory()->inGroup('creatine')->create(['term' => 'kreatin'])))->toBeTrue()
        ->and(phase3Rejects(fn () => SearchIndexOutbox::factory()->forEntity(SearchEntityType::Brand, 9)->create()))->toBeTrue()
        ->and(SearchIndexOutbox::factory()->forEntity(SearchEntityType::Product, 9)->create()->exists)->toBeTrue()
        ->and(phase3Rejects(fn () => SearchClick::factory()->forSearch($search)->create(['entity_id' => 7, 'position' => 2])))->toBeTrue()
        ->and(phase3Rejects(fn () => SearchDemandDaily::factory()->create([
            'date' => $demand->date->toDateString(),
            'market' => $demand->market,
            'query_hash' => $demand->query_hash,
        ])))->toBeTrue();
});

it('deletes the clicks of a search with it and rejects clicks of unknown searches', function () {
    $search = SearchQuery::factory()->create();
    $other = SearchQuery::factory()->create();
    SearchClick::factory()->forSearch($search)->count(2)->sequence(['entity_id' => 1], ['entity_id' => 2])->create();
    SearchClick::factory()->forSearch($other)->create();

    expect($search->clicks()->count())->toBe(2)
        ->and(phase3Rejects(fn () => SearchClick::factory()->create(['search_id' => (string) Str::ulid()])))->toBeTrue();

    $search->delete();

    expect(SearchClick::query()->count())->toBe(1)
        ->and(SearchClick::query()->sole()->search->is($other))->toBeTrue();
});

it('keys recorded searches by ulid and applies the column defaults', function () {
    $search = SearchQuery::factory()->create()->fresh();
    $outbox = SearchIndexOutbox::query()->create(['entity' => SearchEntityType::Product, 'entity_id' => 42])->fresh();

    expect(Str::isUlid($search->search_id))->toBeTrue()
        ->and($search->getKey())->toBe($search->search_id)
        ->and($search->source)->toBe(SearchSource::Page)
        ->and($search->is_bot)->toBeFalse()
        ->and($search->created_at)->not->toBeNull()
        ->and($outbox->priority)->toBeFalse()
        ->and($outbox->queued_at)->not->toBeNull()
        ->and(SearchSynonym::factory()->create()->fresh()->status)->toBe(SynonymStatus::Active);
});

it('builds valid rows from every search factory state', function () {
    $bot = SearchQuery::factory()->bot()->suggest()->create()->fresh();
    $zero = SearchQuery::factory()->zeroResult()->forQuery('xyzzy', 'CZ')->create()->fresh();
    $old = SearchQuery::factory()->oldSession()->create()->fresh();
    $document = SearchDocument::factory()->ofType(SearchEntityType::Brand)->create()->fresh();
    $demand = SearchDemandDaily::factory()->zeroResult()->belowThreshold()->create()->fresh();

    expect($bot->is_bot)->toBeTrue()
        ->and($bot->source)->toBe(SearchSource::Suggest)
        ->and($zero->result_count)->toBe(0)
        ->and($zero->result_refs)->toBe([])
        ->and($zero->market)->toBe('CZ')
        ->and($zero->query_hash)->toBe(hash('sha256', 'xyzzy'))
        ->and($old->occurred_at->lt(now()->subDays(90)))->toBeTrue()
        ->and($old->session_hash)->toHaveLength(64)
        ->and($document->entity_type)->toBe(SearchEntityType::Brand)
        ->and($document->payload)->toHaveKeys(['name', 'slug'])
        ->and($document->updated_at)->not->toBeNull()
        ->and($demand->zero_results)->toBe($demand->searches)
        ->and($demand->sessions)->toBeLessThan(3)
        ->and(SearchSynonym::factory()->disabled()->create()->fresh()->status)->toBe(SynonymStatus::Disabled)
        ->and(SearchIndexOutbox::factory()->priority()->create()->fresh()->priority)->toBeTrue()
        ->and(SearchClick::factory()->create()->fresh()->search)->toBeInstanceOf(SearchQuery::class);
});
