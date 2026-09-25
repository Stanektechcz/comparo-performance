<?php

namespace Tests\Feature\Search\Support;

use App\Domain\Platform\Markets\MarketResolver;
use App\Domain\Platform\PrototypeImport\PrototypeSnapshotImporter;
use App\Domain\Search\Console\SearchReindexer;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Engines\IndexNames;
use App\Models\Country;
use App\Models\SearchDocument;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Meilisearch\Client;
use Throwable;

/**
 * The prototype demo snapshot as search data: imported like
 * tests/Feature/Parity/DatabaseParityTest (zero time shift, the clock frozen
 * at the seed's NOW) with only a few markets active, so a full reindex stays
 * fast (one offer comparison per product × active market).
 */
final class SearchScenario
{
    /**
     * Markets covering the contract cases: product 23 is prescription-only
     * in DE and CZ but allowed in GB; product 22 is `unknown` in DE;
     * merchant 13 (NorthLift) does not ship to CZ.
     */
    public const array MARKETS = ['CZ', 'DE', 'GB'];

    public const string MEILISEARCH_PREFIX = 'comparo_contract_';

    /** @var ?list<array<string, mixed>> search_documents rows of the indexed demo (database engine) */
    private static ?array $databaseRows = null;

    private static ?DateTimeImmutable $anchor = null;

    private static bool $meilisearchIndexed = false;

    /**
     * @param  list<string>  $activeMarkets
     */
    public static function importDemo(array $activeMarkets = self::MARKETS): DateTimeImmutable
    {
        $path = (string) config('comparo.demo.snapshot');
        $anchor = PrototypeSnapshotImporter::seedNow($path);

        (new PrototypeSnapshotImporter($path, $anchor))->run();
        Carbon::setTestNow($anchor);

        Country::query()->whereNotIn('code', $activeMarkets)->update(['is_active' => false]);
        app(MarketResolver::class)->forget();

        return Carbon::now()->toImmutable();
    }

    public static function reindexAll(DateTimeImmutable $now): void
    {
        $reindexer = app(SearchReindexer::class);

        foreach (SearchIndex::cases() as $index) {
            $reindexer->reindex($index, 200, $now);
        }
    }

    public static function useDatabaseEngine(): void
    {
        config(['scout.driver' => 'collection', 'scout.prefix' => '']);
    }

    /**
     * Configures the Meilisearch engine when MEILISEARCH_HOST answers
     * /health; returns the reason to skip otherwise.
     */
    public static function useMeilisearchEngine(): ?string
    {
        $host = (string) env('MEILISEARCH_HOST', '');

        if ($host === '') {
            return 'Meilisearch contract run skipped: MEILISEARCH_HOST is not set.';
        }

        config([
            'scout.driver' => 'meilisearch',
            'scout.prefix' => self::MEILISEARCH_PREFIX,
            'scout.meilisearch.host' => $host,
        ]);

        try {
            if (! app(Client::class)->isHealthy()) {
                return "Meilisearch contract run skipped: {$host} is not healthy.";
            }
        } catch (Throwable) {
            return "Meilisearch contract run skipped: {$host} is not reachable.";
        }

        return null;
    }

    /**
     * The demo, fully indexed by the configured engine. The database engine
     * only reads search_documents (and search_synonyms), so its rows are
     * built once per process and re-inserted for later tests; Meilisearch
     * keeps its indexes between tests and is reindexed once per process.
     */
    public static function indexedDemo(): DateTimeImmutable
    {
        if (config('scout.driver') === 'meilisearch') {
            if (! self::$meilisearchIndexed) {
                $now = self::importDemo();
                self::reindexAll($now);
                self::$anchor ??= $now;
                self::$meilisearchIndexed = true;
            }

            Carbon::setTestNow(self::$anchor ?? Carbon::now());

            return Carbon::now()->toImmutable();
        }

        if (self::$databaseRows === null) {
            $now = self::importDemo();
            self::reindexAll($now);
            self::$anchor = $now;
            self::$databaseRows = array_map(static fn (object $row): array => (array) $row, DB::table('search_documents')->get()->all());

            return $now;
        }

        foreach (array_chunk(self::$databaseRows, 100) as $chunk) {
            DB::table('search_documents')->insert($chunk);
        }

        Carbon::setTestNow(self::$anchor);

        return self::$anchor ?? Carbon::now()->toImmutable();
    }

    /**
     * A stored document payload of a live index (database or Meilisearch).
     *
     * @return array<string, mixed>
     */
    public static function document(SearchIndex $index, int $id): array
    {
        if (config('scout.driver') === 'meilisearch') {
            /** @var array<string, mixed> */
            return app(Client::class)->index(app(IndexNames::class)->live($index))->getDocument($id);
        }

        return SearchDocument::query()
            ->where('index_name', app(IndexNames::class)->live($index))
            ->where('document_id', (string) $id)
            ->firstOrFail()
            ->payload;
    }
}
