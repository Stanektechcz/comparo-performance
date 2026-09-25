<?php

use App\Domain\Catalog\ProductStatus;
use App\Domain\Merchants\MerchantStatus;
use App\Domain\Platform\Markets\MarketResolver;
use App\Domain\Search\Documents\BrandDocumentBuilder;
use App\Domain\Search\Documents\CategoryDocumentBuilder;
use App\Domain\Search\Documents\IndexDocument;
use App\Domain\Search\Documents\IngredientDocumentBuilder;
use App\Domain\Search\Documents\MerchantDocument;
use App\Domain\Search\Documents\MerchantDocumentBuilder;
use App\Domain\Search\Documents\ProductDocument;
use App\Domain\Search\Documents\ProductDocumentBuilder;
use App\Domain\Search\Indexing\DocumentIndexer;
use App\Domain\Search\Local\SearchableEntry;
use App\Domain\Search\Local\SearchableType;
use App\Domain\Search\Queries\ProductMarketSnapshot;
use App\Domain\Search\Relevance\PrototypeRelevance;
use App\Http\Presenters\MoneyPresenter;
use App\Http\Presenters\OfferComparisonPresenter;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\SearchDocument;
use Illuminate\Support\Carbon;
use Tests\Feature\Search\Support\CommercialTerms;
use Tests\Feature\Search\Support\SearchScenario;
use Tests\Support\PrototypeFixtures;

/**
 * Search documents built from the imported prototype demo (CZ, DE, GB
 * active): public fields only, compliance before serialization, deletions
 * for unlisted entities and market data equal to the product page.
 */
beforeEach(function () {
    $this->now = SearchScenario::importDemo();
});

afterEach(fn () => Carbon::setTestNow());

/**
 * @return list<IndexDocument>
 */
function allDemoDocuments(DateTimeImmutable $now): array
{
    $ids = static fn (string $model): array => array_map(intval(...), $model::query()->orderBy('id')->pluck('id')->all());

    return [
        ...app(ProductDocumentBuilder::class)->build($ids(Product::class), $now)->documents,
        ...app(BrandDocumentBuilder::class)->build($ids(Brand::class), $now)->documents,
        ...app(MerchantDocumentBuilder::class)->build($ids(Merchant::class), $now)->documents,
        ...app(CategoryDocumentBuilder::class)->build($ids(Category::class), $now)->documents,
        ...app(IngredientDocumentBuilder::class)->build($ids(Ingredient::class), $now)->documents,
    ];
}

function productDocument(int $id, DateTimeImmutable $now): ProductDocument
{
    return app(ProductDocumentBuilder::class)->build([$id], $now)->documents[0];
}

it('never puts commercial, private or internal fields into a document', function () {
    $documents = allDemoDocuments($this->now);
    $keys = array_values(array_unique(array_merge(...array_map(static fn (IndexDocument $document): array => CommercialTerms::keys($document->toArray()), $documents))));

    expect($documents)->toHaveCount(46 + 13 + 13 + 9 + 28)
        ->and(preg_grep(CommercialTerms::pattern(), $keys))->toBe([])
        ->and(preg_grep('/risk|fraud|complaint|anomal|flag|url|email|phone|reference_price|merchant_sku|feed/i', $keys))->toBe([]);
});

it('carries no price or purchase data for a blocked market', function () {
    $document = productDocument(23, $this->now)->toArray();

    expect($document['markets']['DE'])->toBe(['compliance' => 'blocked', 'purchasable' => false, 'offer_count' => 0, 'in_stock' => false])
        ->and($document['markets']['CZ'])->toBe(['compliance' => 'blocked', 'purchasable' => false, 'offer_count' => 0, 'in_stock' => false])
        ->and($document['blocked_markets'])->toBe(['CZ', 'DE'])
        ->and($document['purchasable_markets'])->toBe(['GB'])
        ->and($document['offer_markets'])->toBe(['GB'])
        ->and($document['markets']['GB'])->toHaveKeys(['min_total_minor', 'currency', 'min_total_eur_minor'])
        ->and(array_keys($document['markets']))->toBe(['CZ', 'DE', 'GB']);
});

it('keeps the informational total of an unknown product but never makes it purchasable', function () {
    $document = productDocument(22, $this->now)->toArray();

    expect($document['markets']['DE']['compliance'])->toBe('unknown')
        ->and($document['markets']['DE']['purchasable'])->toBeFalse()
        ->and($document['markets']['DE']['offer_count'])->toBeGreaterThan(0)
        ->and($document['markets']['DE']['min_total_minor'])->toBeInt()
        ->and($document['unknown_markets'])->toBe(['DE'])
        ->and($document['purchasable_markets'])->not->toContain('DE');
});

it('turns merged, retired and missing products into deletions, in the builder and the index', function () {
    $indexer = app(DocumentIndexer::class);
    $indexer->indexProducts([5, 6, 7], $this->now);

    Product::query()->whereKey(5)->update(['status' => ProductStatus::Merged, 'merged_into_id' => 6, 'merged_at' => $this->now]);
    Product::query()->whereKey(7)->update(['status' => ProductStatus::Retired]);

    $built = app(ProductDocumentBuilder::class)->build([7, 5, 9999, 6, 6], $this->now);
    $report = $indexer->indexProducts([5, 6, 7], $this->now);

    expect(array_map(static fn (ProductDocument $document): int => $document->id, $built->documents))->toBe([6])
        ->and($built->deletedIds)->toBe(['5', '7', '9999'])
        ->and([$report->upserted, $report->deleted])->toBe([1, 2])
        ->and(SearchDocument::query()->where('index_name', 'products')->pluck('document_id')->all())->toBe(['6']);
});

it('reports the same lowest total as the product page', function () {
    $markets = app(MarketResolver::class);
    $states = app(ProductMarketSnapshot::class)->forProducts([1, 6, 22, 23, 36], $this->now);
    $presenter = app(OfferComparisonPresenter::class);
    $checked = 0;

    foreach ($states as $productId => $byMarket) {
        foreach ($byMarket as $code => $state) {
            $page = $presenter->forPage(Product::query()->findOrFail($productId), $markets->resolve($code), $this->now);

            expect(MoneyPresenter::present($state->lowestTotal))->toBe($page['offerSummary']['lowestTotal'], "product {$productId} in {$code}");
            $checked++;
        }
    }

    expect($checked)->toBe(15)
        ->and($states[23]['DE']->lowestTotal)->toBeNull()
        ->and($states[1]['DE']->lowestTotal)->not->toBeNull();
});

it('lists merchants with public trust data and only the active markets they ship to', function () {
    Merchant::query()->whereKey(12)->update(['status' => MerchantStatus::Suspended]);

    $built = app(MerchantDocumentBuilder::class)->build([4, 12, 13], $this->now);
    $documents = collect($built->documents)->keyBy(static fn (MerchantDocument $document): int => $document->id);

    expect($documents->keys()->all())->toBe([4, 13])
        ->and($built->deletedIds)->toBe(['12'])
        ->and($documents[13]->shippingMarkets)->toBe(['DE', 'GB'])
        ->and($documents[4]->shippingMarkets)->toBe(['CZ', 'DE'])
        ->and($documents[13]->websiteHost)->toBe('northlift.co.uk')
        ->and(array_keys($documents[13]->toArray()))->toBe(['id', 'type', 'slug', 'name', 'website_host', 'verified', 'rating', 'shipping_markets', 'indexed_at', 'schema_version']);
});

it('derives website hosts safely', function (?string $website, ?string $host) {
    expect(MerchantDocumentBuilder::host($website))->toBe($host);
})->with([
    ['peaksupps.de', 'peaksupps.de'],
    ['https://www.Example.com/shop?x=1', 'www.example.com'],
    ['', null],
    [null, null],
]);

it('round-trips every document through its stored payload', function () {
    foreach (allDemoDocuments($this->now) as $document) {
        $decoded = json_decode(json_encode($document->toArray(), JSON_PRESERVE_ZERO_FRACTION), true);

        expect($document::fromArray($decoded)->toArray())->toBe($document->toArray());
    }
});

it('gives the local engine entries in prototype order that reproduce the prototype search fixture', function () {
    $documents = allDemoDocuments($this->now);
    $entries = array_map(static fn (IndexDocument $document): SearchableEntry => $document->toSearchableEntry(), $documents);
    $relevance = PrototypeRelevance::prototype();
    $mismatches = [];

    $types = array_map(static fn (SearchableEntry $entry): int => $entry->type->insertionRank(), $entries);
    $sortedTypes = $types;
    sort($sortedTypes);

    foreach (PrototypeFixtures::load('search')['results'] as $record) {
        $expected = array_values(array_filter(
            PrototypeFixtures::searchRecord('results', $record['query'])['list'],
            static fn (array $hit): bool => ! in_array($hit['type'], ['article', 'coupon'], true),
        ));
        $actual = array_map(static function ($hit): array {
            $entry = $hit->entry;
            $reference = $entry->type === SearchableType::Ingredient ? ['name' => $entry->name] : ['id' => $entry->id];

            return ['type' => $entry->type->value, ...$reference, 'score' => $hit->score];
        }, $relevance->rank($record['query'], $entries));

        // Ingredient entities beyond the prototype's S.ingredients (dose-only ingredients) are extra rows here.
        $actual = array_values(array_filter($actual, static fn (array $hit): bool => $hit['type'] !== 'ingredient' || in_array($hit['name'], PrototypeFixtures::seed()['ingredients'], true)));

        if ($actual !== $expected) {
            $mismatches[$record['query']] = compact('expected', 'actual');
        }
    }

    expect($types)->toBe($sortedTypes)
        ->and(array_slice($mismatches, 0, 3, true))->toBe([], sprintf('%d queries differ from the prototype', count($mismatches)));
});
