<?php

namespace App\Domain\Search\Console;

use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Indexing\DocumentIndexer;
use App\Domain\Search\Indexing\IndexingReport;
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

/**
 * Full rebuild of one index without downtime: `<index>_tmp` is emptied,
 * given the current settings, filled in id-ordered chunks and swapped with
 * the live index; the old contents are then dropped. A failure before the
 * swap leaves the live index untouched.
 */
final readonly class SearchReindexer
{
    public const int MAX_CHUNK = 1000;

    public function __construct(
        private SearchEngine $engine,
        private DocumentIndexer $indexer,
        private IndexSettingsFactory $settings,
        private SettingsSynchronizer $synchronizer,
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

        self::source($index)->select('id')->chunkById($chunk, function (Collection $rows) use ($index, $now, $temporary, &$report): void {
            $ids = array_values(array_map(static fn (Model $row): int => (int) $row->getKey(), $rows->all()));
            $report = $report->plus($this->indexer->index($index->entityType(), $ids, $now, $temporary));
        });

        $this->engine->swap($index->value, $temporary);
        $this->engine->flush($temporary);
        $this->synchronizer->remember($settings);

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
