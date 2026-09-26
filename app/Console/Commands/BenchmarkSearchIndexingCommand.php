<?php

namespace App\Console\Commands;

use App\Domain\Search\Benchmark\BenchmarkDatabase;
use App\Domain\Search\Benchmark\BenchmarkIndexer;
use App\Domain\Search\Benchmark\SyntheticCatalog;
use App\Domain\Search\Indexing\DocumentIndexer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Measures search-indexing capacity (F-17): builds a synthetic catalogue in
 * a throwaway SQLite database, activates N markets, runs the database
 * search engine's document build + {@see DocumentIndexer} for every product
 * in `comparo.search.indexing.product_batch`-sized chunks, and prints
 * timings and query counts per chunk and in total.
 *
 * Local/testing only: refuses in production, since it writes a disposable
 * database file and forces the local database search engine regardless of
 * `scout.driver`. Never touches the application database — see
 * {@see BenchmarkDatabase}.
 */
class BenchmarkSearchIndexingCommand extends Command
{
    private const int MAX_PRODUCTS = 20_000;

    /** CountryFactory::MARKETS starts with 20 consecutive EUR markets; staying inside it needs no exchange rates. */
    private const int MAX_MARKETS = 20;

    protected $signature = 'comparo:benchmark:search-indexing
        {--products=500 : Synthetic products to index (1-20000)}
        {--markets=5 : Active markets to activate (1-20, all EUR)}
        {--keep : Keep the throwaway SQLite database file instead of deleting it}';

    protected $description = 'Measure search-indexing capacity: build+index a synthetic catalogue in a throwaway database and print timings';

    public function handle(): int
    {
        if ($this->laravel->environment('production')) {
            $this->error('comparo:benchmark:search-indexing is local/testing only and refuses to run in production.');

            return self::FAILURE;
        }

        $products = $this->boundedInt('products', 1, self::MAX_PRODUCTS);
        $markets = $this->boundedInt('markets', 1, self::MAX_MARKETS);

        if ($products === null || $markets === null) {
            return self::INVALID;
        }

        $database = new BenchmarkDatabase(keep: (bool) $this->option('keep'));

        try {
            $database->open();

            return $this->runBenchmark($products, $markets, $database->path());
        } catch (Throwable $exception) {
            $this->error("Benchmark failed: {$exception->getMessage()}");

            return self::FAILURE;
        } finally {
            $database->close();
        }
    }

    private function runBenchmark(int $products, int $markets, string $databasePath): int
    {
        $this->info("Throwaway database: {$databasePath}");

        $catalog = new SyntheticCatalog;
        $activated = $catalog->activateMarkets($markets);
        $this->info('Activated markets: '.implode(', ', $activated));

        $buildStart = hrtime(true);
        $productIds = $catalog->buildProducts($products);
        $buildMs = (hrtime(true) - $buildStart) / 1_000_000;
        $this->info(sprintf('Built %d synthetic products in %.0f ms.', count($productIds), $buildMs));

        $indexer = $this->databaseBackedIndexer();
        $now = Date::now()->toImmutable();
        $batchSize = max(1, (int) config('comparo.search.indexing.product_batch', 25));

        $chunks = array_chunk($productIds, $batchSize);
        $connection = DB::connection();
        $connection->enableQueryLog();

        $totalNanos = 0;
        $totalQueries = 0;
        $totalUpserted = 0;

        foreach ($chunks as $index => $chunk) {
            $connection->flushQueryLog();
            $start = hrtime(true);
            $report = $indexer->indexProducts($chunk, $now);
            $elapsedNanos = hrtime(true) - $start;
            $queries = count($connection->getQueryLog());

            $totalNanos += $elapsedNanos;
            $totalQueries += $queries;
            $totalUpserted += $report->upserted;

            $comparisons = count($chunk) * $markets;
            $this->line(sprintf(
                'chunk %d/%d (%d products): %.1f ms  |  %.3f ms/product×market  |  %d queries  |  %d upserted',
                $index + 1,
                count($chunks),
                count($chunk),
                $elapsedNanos / 1_000_000,
                $comparisons === 0 ? 0.0 : ($elapsedNanos / 1_000_000) / $comparisons,
                $queries,
                $report->upserted,
            ));
        }

        $connection->disableQueryLog();

        $totalMs = $totalNanos / 1_000_000;
        $totalComparisons = $products * $markets;
        $msPerComparison = $totalComparisons === 0 ? 0.0 : $totalMs / $totalComparisons;
        $msPerProduct = $products === 0 ? 0.0 : $totalMs / $products;

        $this->newLine();
        $this->info(sprintf(
            'Summary: %d products x %d markets (%d comparisons) in %.0f ms total, %d chunks of <=%d, %d queries, %d documents upserted.',
            $products,
            $markets,
            $totalComparisons,
            $totalMs,
            count($chunks),
            $batchSize,
            $totalQueries,
            $totalUpserted,
        ));
        $this->info(sprintf(
            'Summary: %.3f ms/product, %.4f ms/product×market comparison, %.1f queries/chunk.',
            $msPerProduct,
            $msPerComparison,
            count($chunks) === 0 ? 0.0 : $totalQueries / count($chunks),
        ));

        return self::SUCCESS;
    }

    /**
     * A {@see DocumentIndexer} bound to the local database search engine
     * regardless of the app's configured `scout.driver` (Meilisearch may not
     * even be running locally — this benchmark only measures the document
     * build + database write path).
     */
    private function databaseBackedIndexer(): DocumentIndexer
    {
        return BenchmarkIndexer::databaseBacked($this->laravel);
    }

    private function boundedInt(string $option, int $min, int $max): ?int
    {
        $value = filter_var($this->option($option), FILTER_VALIDATE_INT);

        if ($value === false || $value < $min || $value > $max) {
            $this->error("The --{$option} option must be an integer between {$min} and {$max}.");

            return null;
        }

        return $value;
    }
}
