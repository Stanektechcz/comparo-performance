<?php

use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Search\Local\EntryAttributes;
use App\Domain\Search\Local\LocalQueryEvaluator;
use App\Domain\Search\Local\LocalSearchResult;
use App\Domain\Search\Local\MarketAttributes;
use App\Domain\Search\Local\MarketVisibility;
use App\Domain\Search\Local\SearchableEntry;
use App\Domain\Search\Local\SearchableType;
use App\Domain\Search\Query\SearchFilters;
use App\Domain\Search\Query\SearchQuery;
use App\Domain\Search\Query\SortOption;
use App\Domain\Search\Relevance\RelevanceHit;
use Tests\Support\PrototypeFixtures;

/**
 * A product visible in DE with the given lowest total (EUR minor) and rating.
 */
function wheyProduct(int $id, string $name, ?int $totalMinor, ?float $rating = null, int $ratingCount = 0, ComplianceStatus $compliance = ComplianceStatus::Allowed, bool $inStock = true, string $brand = 'ironforge', array $ingredients = ['whey-isolate']): SearchableEntry
{
    $blocked = $compliance->isBlocked();

    return SearchableEntry::product($id, $name, brandName: 'Brand', categoryName: 'Protein', attributes: new EntryAttributes(
        brandSlug: $brand,
        categoryPath: ['protein'],
        ingredientSlugs: $ingredients,
        markets: ['DE' => new MarketAttributes(
            compliance: $compliance,
            purchasable: $compliance->isPurchasable(),
            minTotalMinor: $blocked ? null : $totalMinor,
            minTotalEurMinor: $blocked ? null : $totalMinor,
            inStock: ! $blocked && $inStock,
        )],
        ratingAverage: $rating,
        ratingCount: $ratingCount,
    ));
}

/**
 * @return list<int|string>
 */
function hitIds(LocalSearchResult $result): array
{
    return array_map(static fn (RelevanceHit $hit): int|string => $hit->entry->id, $result->hits);
}

function evaluateWhey(array $entries, array $query = [], ?Closure $visibility = null): LocalSearchResult
{
    return LocalQueryEvaluator::prototype()->evaluate(new SearchQuery(...['text' => 'whey', 'market' => 'DE', ...$query]), $entries, $visibility);
}

it('matches the prototype relevance order over the seed catalogue when nothing is hidden', function () {
    $entries = PrototypeFixtures::searchEntries();
    $expected = array_values(array_filter(
        PrototypeFixtures::searchRecord('results', 'whey isolate')['list'],
        static fn (array $hit): bool => ! in_array($hit['type'], ['article', 'coupon'], true),
    ));

    $result = LocalQueryEvaluator::prototype()->evaluate(new SearchQuery('whey isolate', 'DE', perPage: 100), $entries, MarketVisibility::everything());

    expect(array_map(PrototypeFixtures::hitAsPrototype(...), $result->hits))->toBe($expected)
        ->and($result->total)->toBe(count($expected));
});

it('drops products blocked in the market and products without data for it, keeping unknown ones', function () {
    $entries = [
        wheyProduct(1, 'Whey One', 1990),
        wheyProduct(2, 'Whey Two', null, compliance: ComplianceStatus::NotAllowed),
        wheyProduct(3, 'Whey Three', 2490, compliance: ComplianceStatus::Unknown),
        wheyProduct(4, 'Whey Four', null, compliance: ComplianceStatus::PrescriptionOnly),
        SearchableEntry::product(5, 'Whey Five'),
        SearchableEntry::brand(1, 'Whey Brand'),
    ];

    $result = evaluateWhey($entries);

    expect(hitIds($result))->toBe([1, 1, 3])
        ->and($result->facets->typeCounts['product'])->toBe(2)
        ->and($result->facets->brands)->toHaveCount(1)
        ->and($result->facets->brands[0]->count)->toBe(2);
});

it('applies typed filters to products only and counts facets after filtering', function (array $filters, array $expectedIds) {
    $entries = [
        wheyProduct(1, 'Whey One', 1990, rating: 4.8, brand: 'ironforge'),
        wheyProduct(2, 'Whey Two', 2990, rating: 3.9, inStock: false, brand: 'biopeak', ingredients: ['whey-concentrate']),
        wheyProduct(3, 'Whey Three', 2490, compliance: ComplianceStatus::Unknown, brand: 'biopeak'),
        SearchableEntry::shop(7, 'Whey Shop'),
    ];

    $result = evaluateWhey($entries, ['filters' => new SearchFilters(...$filters)]);

    expect(hitIds($result))->toBe($expectedIds)
        ->and($result->facets->typeCounts['all'])->toBe(count($expectedIds));
})->with([
    'no filters' => [[], [7, 1, 2, 3]],
    'brand' => [['brandSlugs' => ['biopeak']], [7, 2, 3]],
    'category' => [['categorySlugs' => ['creatine']], [7]],
    'ingredient' => [['ingredientSlugs' => ['whey-concentrate', 'casein']], [7, 2]],
    'in stock' => [['inStock' => true], [7, 1, 3]],
    'rating' => [['minRating' => 4.0], [7, 1]],
    'price range inclusive' => [['priceMinMinor' => 2490, 'priceMaxMinor' => 2990], [7, 2, 3]],
    'price ceiling' => [['priceMaxMinor' => 1989], [7]],
]);

it('orders by price, rating and name with deterministic tie-breaks', function (SortOption $sort, array $expectedIds) {
    $entries = [
        wheyProduct(1, 'Whey Delta', 2490, rating: 4.0, ratingCount: 5),
        wheyProduct(2, 'whey alpha', 1990, rating: 4.5, ratingCount: 2),
        wheyProduct(3, 'Whey Charlie', 2490, rating: 4.0, ratingCount: 9),
        wheyProduct(4, 'Whey Bravo', 2490, rating: null),
        SearchableEntry::brand(9, 'Whey Echo'),
    ];

    expect(hitIds(evaluateWhey($entries, ['sort' => $sort])))->toBe($expectedIds);
})->with([
    // Brand scores 94 (92 + 2) against 92 for every product.
    'relevance keeps insertion order' => [SortOption::Relevance, [9, 1, 2, 3, 4]],
    // Equal prices: rating count desc (3 before 1), then name; unpriced last.
    'price ascending' => [SortOption::PriceAsc, [2, 3, 1, 4, 9]],
    'rating descending' => [SortOption::Rating, [2, 3, 1, 9, 4]],
    'name folded' => [SortOption::Name, [2, 4, 3, 1, 9]],
]);

it('paginates the selected type tab and reports the total', function () {
    $entries = [
        ...array_map(static fn (int $id): SearchableEntry => wheyProduct($id, "Whey {$id}", 1000 + $id), range(1, 5)),
        SearchableEntry::brand(1, 'Whey Brand'),
    ];

    $second = evaluateWhey($entries, ['type' => SearchableType::Product, 'page' => 2, 'perPage' => 2]);
    $beyond = evaluateWhey($entries, ['type' => SearchableType::Product, 'page' => 4, 'perPage' => 2]);

    expect(hitIds($second))->toBe([3, 4])
        ->and($second->total)->toBe(5)
        ->and($second->lastPage())->toBe(3)
        ->and($second->facets->typeCounts['all'])->toBe(6)
        ->and(hitIds($beyond))->toBe([])
        ->and($beyond->total)->toBe(5);
});

it('never suggests a product hidden in the market when nothing matched', function () {
    $entries = [
        wheyProduct(1, 'Creatine Pure', 1990),
        wheyProduct(2, 'Gummies Extreme', null, compliance: ComplianceStatus::NotAllowed),
    ];

    $result = LocalQueryEvaluator::prototype()->evaluate(new SearchQuery('gummies', 'DE'), $entries);
    $everything = LocalQueryEvaluator::prototype()->evaluate(new SearchQuery('gummies', 'DE'), $entries, MarketVisibility::everything());

    expect($result->isEmpty())->toBeTrue()
        ->and($result->didYouMean)->toBe([])
        ->and(hitIds($everything))->toBe([2]);
});

it('returns an empty page without suggestions for a query below the minimum length', function () {
    $result = evaluateWhey([wheyProduct(1, 'Whey', 1990)], ['text' => ' w ']);

    expect($result->hits)->toBe([])
        ->and($result->query->searchable)->toBeFalse()
        ->and($result->didYouMean)->toBe([]);
});
