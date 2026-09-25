<?php

namespace App\Domain\Search\Indexing;

use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Documents\BrandDocumentBuilder;
use App\Domain\Search\Documents\BuiltDocuments;
use App\Domain\Search\Documents\CategoryDocumentBuilder;
use App\Domain\Search\Documents\IndexDocument;
use App\Domain\Search\Documents\IngredientDocumentBuilder;
use App\Domain\Search\Documents\MerchantDocumentBuilder;
use App\Domain\Search\Documents\ProductDocumentBuilder;
use App\Domain\Search\SearchEntityType;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Builds documents for ids and writes them: upserts for listed entities,
 * deletions for the rest (merged, retired, deactivated, missing). Idempotent,
 * so the outbox processor can retry any batch. `$index` defaults to the
 * live index; the reindex command targets `<index>_tmp`.
 */
final readonly class DocumentIndexer
{
    public function __construct(
        private SearchEngine $engine,
        private ProductDocumentBuilder $products,
        private BrandDocumentBuilder $brands,
        private CategoryDocumentBuilder $categories,
        private IngredientDocumentBuilder $ingredients,
        private MerchantDocumentBuilder $merchants,
    ) {}

    /**
     * @param  list<int>  $ids
     */
    public function index(SearchEntityType $entity, array $ids, DateTimeImmutable $now, ?string $index = null): IndexingReport
    {
        $built = match ($entity) {
            SearchEntityType::Product => $this->products->build($ids, $now),
            SearchEntityType::Brand => $this->brands->build($ids, $now),
            SearchEntityType::Category => $this->categories->build($ids, $now),
            SearchEntityType::Ingredient => $this->ingredients->build($ids, $now),
            SearchEntityType::Merchant => $this->merchants->build($ids, $now),
        };

        return $this->write($built, $index ?? SearchIndex::forEntity($entity)->value);
    }

    /**
     * @param  list<int>  $ids
     */
    public function indexProducts(array $ids, DateTimeImmutable $now, ?string $index = null): IndexingReport
    {
        return $this->index(SearchEntityType::Product, $ids, $now, $index);
    }

    /**
     * @param  list<int>  $ids
     */
    public function indexBrands(array $ids, DateTimeImmutable $now, ?string $index = null): IndexingReport
    {
        return $this->index(SearchEntityType::Brand, $ids, $now, $index);
    }

    /**
     * @param  list<int>  $ids
     */
    public function indexCategories(array $ids, DateTimeImmutable $now, ?string $index = null): IndexingReport
    {
        return $this->index(SearchEntityType::Category, $ids, $now, $index);
    }

    /**
     * @param  list<int>  $ids
     */
    public function indexIngredients(array $ids, DateTimeImmutable $now, ?string $index = null): IndexingReport
    {
        return $this->index(SearchEntityType::Ingredient, $ids, $now, $index);
    }

    /**
     * @param  list<int>  $ids
     */
    public function indexMerchants(array $ids, DateTimeImmutable $now, ?string $index = null): IndexingReport
    {
        return $this->index(SearchEntityType::Merchant, $ids, $now, $index);
    }

    /**
     * @param  BuiltDocuments<IndexDocument>  $built
     */
    private function write(BuiltDocuments $built, string $index): IndexingReport
    {
        if (SearchIndex::fromName($index) !== $built->index) {
            throw new InvalidArgumentException("Documents of [{$built->index->value}] cannot be written to [{$index}].");
        }

        $this->engine->upsert($index, $built->searchDocuments());
        $this->engine->delete($index, $built->deletedIds);

        return new IndexingReport(count($built->documents), count($built->deletedIds));
    }
}
