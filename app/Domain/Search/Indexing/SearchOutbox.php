<?php

namespace App\Domain\Search\Indexing;

use App\Domain\Search\Jobs\ProcessSearchOutbox;
use App\Domain\Search\SearchEntityType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Writes pending search index updates to the transactional outbox
 * (`search_index_outbox`, docs/architecture/phase-3-search.md §5).
 *
 * One row per entity: a repeated enqueue merges into the existing row
 * (priority = old OR new, `queued_at` refreshed). `queued_at` always moves
 * forward — at least one second past the stored value — so the processor,
 * which deletes a row only while it still carries the `queued_at` it read,
 * never drops a row that was re-enqueued while it was being indexed (the
 * column has second precision).
 *
 * Callers are after-commit listeners and hooks; every chunk is written in its
 * own transaction. Fan-outs (merchant, brand, category, ingredient, full
 * reindex) read ids with keyset pagination in `comparo.search.indexing.fan_out_chunk`
 * batches, never loading an unbounded list.
 */
final class SearchOutbox
{
    public const string TABLE = 'search_index_outbox';

    public const int DEFAULT_CHUNK = 500;

    /**
     * @param  list<int>  $ids
     * @return int rows written
     */
    public function enqueue(SearchEntityType $entity, array $ids, bool $priority = false): int
    {
        $ids = self::normalize($ids);

        foreach (array_chunk($ids, self::chunk()) as $chunk) {
            DB::transaction(fn () => $this->write($entity, $chunk, $priority));
        }

        if ($priority && $ids !== []) {
            ProcessSearchOutbox::dispatch(priorityOnly: true)->afterCommit();
        }

        return count($ids);
    }

    /**
     * The merchant document and every product the merchant has an active
     * offer for.
     */
    public function enqueueMerchant(int $merchantId, bool $priority = false): int
    {
        return $this->enqueue(SearchEntityType::Merchant, [$merchantId], $priority)
            + $this->enqueueMerchantProducts($merchantId, $priority);
    }

    /**
     * Products the merchant has an active offer for, in id-ordered chunks.
     */
    public function enqueueMerchantProducts(int $merchantId, bool $priority = false): int
    {
        return $this->fanOut(
            SearchEntityType::Product,
            static fn (): Builder => Offer::query()->where('merchant_id', $merchantId)->where('is_active', true),
            'product_id',
            $priority,
        );
    }

    /**
     * Products of a brand (their documents carry the brand name, slug and aliases).
     */
    public function enqueueBrandProducts(int $brandId): int
    {
        return $this->fanOut(SearchEntityType::Product, static fn (): Builder => Product::query()->where('brand_id', $brandId), 'id');
    }

    /**
     * Products of the given categories (their documents carry the category name and slug path).
     *
     * @param  list<int>  $categoryIds
     */
    public function enqueueCategoryProducts(array $categoryIds): int
    {
        $categoryIds = self::normalize($categoryIds);

        return $categoryIds === [] ? 0 : $this->fanOut(SearchEntityType::Product, static fn (): Builder => Product::query()->whereIn('category_id', $categoryIds), 'id');
    }

    /**
     * Products listing the ingredient (their documents carry its name and slug).
     */
    public function enqueueIngredientProducts(int $ingredientId): int
    {
        $total = 0;
        $after = 0;
        $chunk = self::chunk();

        do {
            /** @var list<int> $ids */
            $ids = DB::table('ingredient_product')
                ->where('ingredient_id', $ingredientId)
                ->where('product_id', '>', $after)
                ->orderBy('product_id')
                ->limit($chunk)
                ->pluck('product_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $total += $this->enqueue(SearchEntityType::Product, $ids);
            $after = $ids === [] ? $after : $ids[array_key_last($ids)];
        } while (count($ids) >= $chunk);

        return $total;
    }

    /**
     * Every entity that gets a document (listed products and merchants, all
     * brands, categories and ingredients — the reindex command's sources).
     */
    public function enqueueAll(SearchEntityType $entity): int
    {
        return $this->fanOut($entity, static fn (): Builder => match ($entity) {
            SearchEntityType::Product => Product::query()->listed(),
            SearchEntityType::Merchant => Merchant::query()->listed(),
            SearchEntityType::Brand => Brand::query(),
            SearchEntityType::Category => Category::query(),
            SearchEntityType::Ingredient => Ingredient::query(),
        }, 'id');
    }

    /**
     * Keyset pagination over a (possibly repeated) id column: each batch reads
     * the next distinct ids after the last one seen and enqueues them.
     *
     * @param  Closure(): Builder<covariant Model>  $query
     */
    private function fanOut(SearchEntityType $entity, Closure $query, string $column, bool $priority = false): int
    {
        $total = 0;
        $after = 0;
        $chunk = self::chunk();

        do {
            /** @var list<int> $ids */
            $ids = $query()
                ->where($column, '>', $after)
                ->distinct()
                ->orderBy($column)
                ->limit($chunk)
                ->pluck($column)
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $total += $this->enqueue($entity, $ids, $priority);
            $after = $ids === [] ? $after : $ids[array_key_last($ids)];
        } while (count($ids) >= $chunk);

        return $total;
    }

    /**
     * Upserts one chunk. The existing rows are locked first so concurrent
     * enqueues of the same entity serialise and each one moves `queued_at`
     * forward. A non-priority enqueue never clears an existing priority.
     *
     * @param  list<int>  $ids
     */
    private function write(SearchEntityType $entity, array $ids, bool $priority): void
    {
        /** @var array<int, string> $existing entity id => stored queued_at */
        $existing = DB::table(self::TABLE)
            ->where('entity', $entity->value)
            ->whereIn('entity_id', $ids)
            ->lockForUpdate()
            ->pluck('queued_at', 'entity_id')
            ->mapWithKeys(static fn (mixed $queuedAt, mixed $id): array => [(int) $id => (string) $queuedAt])
            ->all();

        $now = CarbonImmutable::instance(Date::now())->startOfSecond();
        $rows = [];

        foreach ($ids as $id) {
            $queuedAt = $now;

            if (isset($existing[$id])) {
                $stored = CarbonImmutable::parse($existing[$id]);
                $queuedAt = $stored->greaterThanOrEqualTo($now) ? $stored->addSecond() : $now;
            }

            $rows[] = [
                'entity' => $entity->value,
                'entity_id' => $id,
                'priority' => $priority,
                'queued_at' => $queuedAt->format('Y-m-d H:i:s'),
            ];
        }

        DB::table(self::TABLE)->upsert($rows, ['entity', 'entity_id'], $priority ? ['priority', 'queued_at'] : ['queued_at']);
    }

    /**
     * @param  list<int>  $ids
     * @return list<int> positive, unique, ascending
     */
    private static function normalize(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        sort($ids);

        return $ids;
    }

    /**
     * @return int<1, max>
     */
    private static function chunk(): int
    {
        return max(1, (int) config('comparo.search.indexing.fan_out_chunk', self::DEFAULT_CHUNK));
    }
}
