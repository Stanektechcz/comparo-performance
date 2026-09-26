<?php

namespace App\Domain\Search\Console;

use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Indexing\DocumentIndexer;
use App\Domain\Search\Indexing\IndexingReport;
use App\Domain\Search\Indexing\RebuildMarker;
use App\Domain\Search\Indexing\SearchOutbox;
use App\Domain\Search\Settings\IndexSettingsFactory;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Merchant;
use App\Models\Product;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Throwable;

/**
 * Full rebuild of one index without downtime: `<index>_tmp` is emptied,
 * given the current settings, filled in id-ordered chunks and swapped with
 * the live index; the old contents are then dropped. A failure before the
 * swap leaves the live index untouched.
 *
 * The outbox keeps indexing live changes meanwhile. So that none is lost at
 * the swap, the rebuild is announced with a {@see RebuildMarker} before the
 * first chunk: live writes then also go to the twin, and their ids are
 * recorded. After the swap every recorded id is re-enqueued with priority,
 * which corrects a chunk that was built before a change but written after
 * that change was dual-written (docs/architecture/phase-3-search.md §5).
 */
final readonly class SearchReindexer
{
    public const int MAX_CHUNK = 1000;

    public function __construct(
        private SearchEngine $engine,
        private DocumentIndexer $indexer,
        private IndexSettingsFactory $settings,
        private SettingsSynchronizer $synchronizer,
        private RebuildMarker $rebuilds,
        private SearchOutbox $outbox,
    ) {}

    public function reindex(SearchIndex $index, int $chunk, DateTimeImmutable $now): IndexingReport
    {
        if ($chunk < 1 || $chunk > self::MAX_CHUNK) {
            throw new InvalidArgumentException('The chunk size lies between 1 and '.self::MAX_CHUNK.'.');
        }

        $temporary = $index->temporary();
        $settings = $this->settings->for($index);
        $report = new IndexingReport(0, 0);

        $this->engine->flush($temporary);
        $this->engine->applySettings($settings->forIndex($temporary));
        $rebuild = $this->rebuilds->begin($index, $now);

        try {
            self::source($index)->select('id')->chunkById($chunk, function (Collection $rows) use ($index, $now, $temporary, &$report): void {
                $ids = array_values(array_map(static fn (Model $row): int => (int) $row->getKey(), $rows->all()));
                $report = $report->plus($this->indexer->index($index->entityType(), $ids, $now, $temporary));
            });

            $this->engine->swap($index->value, $temporary);
        } catch (Throwable $exception) {
            // The live index is untouched: nothing recorded needs correcting.
            $this->rebuilds->finish($rebuild);

            throw $exception;
        }

        $changed = $this->rebuilds->finish($rebuild);
        $this->engine->flush($temporary);
        $this->synchronizer->remember($settings);
        $this->outbox->enqueue($index->entityType(), $changed, priority: true);

        return $report;
    }

    /**
     * The entities that get documents (listed products and merchants; every
     * brand, category and ingredient).
     *
     * @return Builder<covariant Model>
     */
    private static function source(SearchIndex $index): Builder
    {
        return match ($index) {
            SearchIndex::Products => Product::query()->listed(),
            SearchIndex::Brands => Brand::query(),
            SearchIndex::Merchants => Merchant::query()->listed(),
            SearchIndex::Categories => Category::query(),
            SearchIndex::Ingredients => Ingredient::query(),
        };
    }
}
