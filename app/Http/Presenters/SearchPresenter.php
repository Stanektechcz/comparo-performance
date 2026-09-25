<?php

namespace App\Http\Presenters;

use App\Domain\Compliance\Queries\ComplianceResolver;
use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Search\Contracts\SearchHit;
use App\Domain\Search\Contracts\SearchResults;
use App\Domain\Search\Contracts\SpellingSuggestion;
use App\Domain\Search\Facets\FacetValue;
use App\Domain\Search\Facets\SearchFacets;
use App\Domain\Search\Local\SearchableType;
use App\Domain\Search\Query\SortOption;
use App\Http\Requests\Search\SearchRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Merchant;
use App\Models\Product;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Serializes the public search page (docs/architecture/phase-3-search.md §4).
 *
 * Hits are references only: every type is loaded with ONE batched query and
 * then re-checked against the live database (defence in depth against a
 * stale index, invariant 2):
 * - products must still be listed and are re-decided for the current market
 *   with ComplianceResolver::decideMany — blocked products are dropped,
 *   `unknown` ones keep the informational lowest total but are never
 *   purchasable; the lowest landed total comes from the cached offer
 *   comparison (ProductPresenter::summaries), never from the index;
 * - shops must still be listed and ship to the market (A-28).
 * Links are site-relative; no outbound link and no purchase URL is ever
 * serialized here.
 *
 * @phpstan-type Criteria array{q: string, type: string, sort: string, page: int, brand: list<string>, category: list<string>, ingredient: list<string>, price_min: ?int, price_max: ?int, in_stock: bool, min_rating: ?int}
 */
final class SearchPresenter
{
    public const int BROWSE_CATEGORIES = 8;

    public const int DID_YOU_MEAN = 3;

    private const array NO_COUNTS = ['all' => 0, 'product' => 0, 'brand' => 0, 'shop' => 0, 'category' => 0, 'ingredient' => 0];

    private const array TAB_LABELS = [
        'all' => 'All',
        'product' => 'Products',
        'brand' => 'Brands',
        'shop' => 'Shops',
        'category' => 'Categories',
        'ingredient' => 'Ingredients',
    ];

    private const array SORT_LABELS = [
        'relevance' => 'Best match',
        'price_asc' => 'Lowest total price',
        'rating' => 'Highest rated',
        'name' => 'Name (A–Z)',
    ];

    public function __construct(
        private readonly ComplianceResolver $compliance,
        private readonly ProductPresenter $products,
        private readonly SeoPresenter $seo,
    ) {}

    /**
     * @param  Criteria  $criteria
     */
    public function present(SearchResults $results, array $criteria, MarketContext $market, DateTimeImmutable $now): SearchPresentation
    {
        ['cards' => $cards, 'refs' => $refs] = $this->cards($results->hits, $market, $now);
        $total = max(0, $results->total - (count($results->hits) - count($cards)));
        $lastPage = min(SearchRequest::MAX_PAGE, $results->lastPage());
        $didYouMean = $total === 0 ? self::didYouMean($results->didYouMean) : [];

        return new SearchPresentation([
            ...$this->frame($criteria, $market, true),
            'tabs' => self::tabs($results->facets->typeCounts),
            'results' => $cards,
            'facets' => self::facets($results->facets, $criteria),
            'pagination' => [
                'currentPage' => $results->page,
                'lastPage' => $lastPage,
                'perPage' => $results->perPage,
                'total' => $total,
                'links' => [
                    'prev' => $results->page > 1 ? self::url($criteria, ['page' => $results->page - 1]) : null,
                    'next' => $results->page < $lastPage ? self::url($criteria, ['page' => $results->page + 1]) : null,
                ],
            ],
            'didYouMean' => $didYouMean,
            'browseCategories' => $total === 0 && $didYouMean === [] ? self::browseCategories() : [],
        ], $refs, $total);
    }

    /**
     * The page for a text below the searchable minimum: no engine call, no
     * results, categories to browse instead.
     *
     * @param  Criteria  $criteria
     * @return array<string, mixed>
     */
    public function empty(array $criteria, MarketContext $market): array
    {
        return [
            ...$this->frame($criteria, $market, false),
            'tabs' => self::tabs(self::NO_COUNTS),
            'results' => [],
            'facets' => self::facets(new SearchFacets(self::NO_COUNTS, [], [], []), $criteria),
            'pagination' => ['currentPage' => 1, 'lastPage' => 1, 'perPage' => SearchRequest::PER_PAGE, 'total' => 0, 'links' => ['prev' => null, 'next' => null]],
            'didYouMean' => [],
            'browseCategories' => self::browseCategories(),
        ];
    }

    /**
     * A site-relative /search URL for the criteria with overrides; default
     * values are left out so URLs stay short and canonical-looking.
     *
     * @param  Criteria  $criteria
     * @param  array<string, mixed>  $overrides
     */
    public static function url(array $criteria, array $overrides = []): string
    {
        $values = [...$criteria, ...$overrides];
        $query = array_filter([
            'q' => $values['q'] === '' ? null : $values['q'],
            'type' => $values['type'] === SearchRequest::ALL_TYPES ? null : $values['type'],
            'brand' => $values['brand'] === [] ? null : $values['brand'],
            'category' => $values['category'] === [] ? null : $values['category'],
            'ingredient' => $values['ingredient'] === [] ? null : $values['ingredient'],
            'price_min' => $values['price_min'],
            'price_max' => $values['price_max'],
            'in_stock' => $values['in_stock'] ? 1 : null,
            'min_rating' => $values['min_rating'],
            'sort' => $values['sort'] === SortOption::Relevance->value ? null : $values['sort'],
            'page' => $values['page'] > 1 ? $values['page'] : null,
        ], static fn (mixed $value): bool => $value !== null);

        return route('search', $query, false);
    }

    /**
     * Props shared by the result and the empty page.
     *
     * @param  Criteria  $criteria
     * @return array<string, mixed>
     */
    private function frame(array $criteria, MarketContext $market, bool $searchable): array
    {
        $title = $searchable ? 'Search results for “'.mb_strimwidth($criteria['q'], 0, 60, '…').'”' : 'Search';

        return [
            'seo' => $this->seo->page(
                $title,
                'Search sports nutrition products, brands, shops, categories and ingredients with total landed prices for your market.',
                route('search'),
                robots: 'noindex,follow',
            ),
            'query' => $criteria,
            'searchable' => $searchable,
            'priceCurrency' => $market->currency,
            'sortOptions' => array_map(
                static fn (SortOption $sort): array => ['value' => $sort->value, 'label' => self::SORT_LABELS[$sort->value]],
                SortOption::cases(),
            ),
        ];
    }

    /**
     * @param  list<SearchHit>  $hits
     * @return array{cards: list<array<string, mixed>>, refs: list<array{type: string, id: int}>}
     */
    private function cards(array $hits, MarketContext $market, DateTimeImmutable $now): array
    {
        $ids = [];
        foreach ($hits as $hit) {
            $ids[$hit->type->value][] = (int) $hit->id;
        }

        $loaded = [
            SearchableType::Product->value => $this->productCards($ids[SearchableType::Product->value] ?? [], $market, $now),
            SearchableType::Brand->value => self::brandCards($ids[SearchableType::Brand->value] ?? []),
            SearchableType::Shop->value => self::shopCards($ids[SearchableType::Shop->value] ?? [], $market),
            SearchableType::Category->value => self::categoryCards($ids[SearchableType::Category->value] ?? []),
            SearchableType::Ingredient->value => self::ingredientCards($ids[SearchableType::Ingredient->value] ?? []),
        ];

        $cards = [];
        $refs = [];
        foreach ($hits as $hit) {
            $card = $loaded[$hit->type->value][(int) $hit->id] ?? null;

            if ($card !== null) {
                $refs[] = ['type' => $hit->type->value, 'id' => (int) $hit->id];
                $cards[] = ['type' => $hit->type->value, 'id' => (int) $hit->id, 'position' => count($refs), ...$card];
            }
        }

        return ['cards' => $cards, 'refs' => $refs];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function productCards(array $ids, MarketContext $market, DateTimeImmutable $now): array
    {
        if ($ids === []) {
            return [];
        }

        $products = Product::query()->listed()->whereKey($ids)->with(['brand', 'category'])->get();
        $decisions = $this->compliance->decideMany(array_values(array_map(static fn (Product $product): int => $product->id, $products->all())), $market);
        $visible = $products->filter(static fn (Product $product): bool => ! $decisions[$product->id]->status->isBlocked())->values();
        $cards = [];

        foreach ($this->products->summaries(new Collection($visible->all()), $market, $now) as $summary) {
            $status = $decisions[$summary['id']]->status;
            $cards[(int) $summary['id']] = [
                'href' => route('products.show', $summary['slug'], false),
                'product' => $summary,
                'compliance' => ['status' => $status->value, 'label' => $status->label(), 'purchasable' => $status->isPurchasable()],
            ];
        }

        return $cards;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private static function brandCards(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Brand::query()->whereKey($ids)->withCount(['products' => static fn ($query) => $query->listed()])->get()
            ->mapWithKeys(static fn (Brand $brand): array => [$brand->id => [
                'href' => route('brands.show', $brand->slug, false),
                'name' => $brand->name,
                'slug' => $brand->slug,
                'productCount' => (int) $brand->getAttribute('products_count'),
            ]])->all();
    }

    /**
     * Only listed shops with a shipping zone for the market (A-28).
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private static function shopCards(array $ids, MarketContext $market): array
    {
        if ($ids === [] || $market->countryId === null) {
            return [];
        }

        return Merchant::query()->listed()->whereKey($ids)
            ->whereHas('shippingZones', static fn ($query) => $query->where('country_id', $market->countryId))
            ->get()
            ->mapWithKeys(static fn (Merchant $shop): array => [$shop->id => [
                'href' => route('shops.show', $shop->slug, false),
                'name' => $shop->name,
                'slug' => $shop->slug,
                'verified' => $shop->isVerified(),
            ]])->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private static function categoryCards(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Category::query()->whereKey($ids)->get()
            ->mapWithKeys(static fn (Category $category): array => [$category->id => [
                'href' => route('categories.show', $category->slug, false),
                'name' => $category->name,
                'slug' => $category->slug,
            ]])->all();
    }

    /**
     * Ingredients have no page of their own yet: they link to a product
     * search filtered by the ingredient.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private static function ingredientCards(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Ingredient::query()->whereKey($ids)->get()
            ->mapWithKeys(static fn (Ingredient $ingredient): array => [$ingredient->id => [
                'href' => route('search', ['q' => $ingredient->name, 'type' => SearchableType::Product->value, 'ingredient' => [$ingredient->slug]], false),
                'name' => $ingredient->name,
                'slug' => $ingredient->slug,
            ]])->all();
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{key: string, label: string, count: int}>
     */
    private static function tabs(array $counts): array
    {
        $tabs = [];
        foreach (self::TAB_LABELS as $key => $label) {
            $tabs[] = ['key' => $key, 'label' => $label, 'count' => (int) ($counts[$key] ?? 0)];
        }

        return $tabs;
    }

    /**
     * Facet options with display names; selected values missing from the
     * result facets are kept (count 0) so they can be unselected.
     *
     * @param  Criteria  $criteria
     * @return array{brands: list<array{value: string, label: string, count: int, selected: bool}>, categories: list<array{value: string, label: string, count: int, selected: bool}>, ingredients: list<array{value: string, label: string, count: int, selected: bool}>}
     */
    private static function facets(SearchFacets $facets, array $criteria): array
    {
        return [
            'brands' => self::facetOptions($facets->brands, $criteria['brand'], static fn (array $slugs): array => Brand::query()->whereIn('slug', $slugs)->pluck('name', 'slug')->all()),
            'categories' => self::facetOptions($facets->categories, $criteria['category'], static fn (array $slugs): array => Category::query()->whereIn('slug', $slugs)->pluck('name', 'slug')->all()),
            'ingredients' => self::facetOptions($facets->ingredients, $criteria['ingredient'], static fn (array $slugs): array => Ingredient::query()->whereIn('slug', $slugs)->pluck('name', 'slug')->all()),
        ];
    }

    /**
     * @param  list<FacetValue>  $values
     * @param  list<string>  $selected
     * @param  callable(list<string>): array<string, string>  $names
     * @return list<array{value: string, label: string, count: int, selected: bool}>
     */
    private static function facetOptions(array $values, array $selected, callable $names): array
    {
        $counts = [];
        foreach ($values as $value) {
            $counts[$value->value] = $value->count;
        }

        foreach ($selected as $slug) {
            $counts[$slug] ??= 0;
        }

        if ($counts === []) {
            return [];
        }

        $labels = $names(array_map(strval(...), array_keys($counts)));
        $options = [];

        foreach ($counts as $slug => $count) {
            $slug = (string) $slug;
            $options[] = ['value' => $slug, 'label' => (string) ($labels[$slug] ?? $slug), 'count' => $count, 'selected' => in_array($slug, $selected, true)];
        }

        return $options;
    }

    /**
     * @param  list<SpellingSuggestion>  $suggestions
     * @return list<array{label: string, href: string}>
     */
    private static function didYouMean(array $suggestions): array
    {
        $labels = array_slice(array_values(array_unique(array_map(static fn (SpellingSuggestion $suggestion): string => $suggestion->label, $suggestions))), 0, self::DID_YOU_MEAN);

        return array_map(static fn (string $label): array => ['label' => $label, 'href' => route('search', ['q' => $label], false)], $labels);
    }

    /**
     * @return list<array{slug: string, name: string, href: string}>
     */
    private static function browseCategories(): array
    {
        return array_values(Category::query()->whereNull('parent_id')->orderBy('name')->limit(self::BROWSE_CATEGORIES)->get()
            ->map(static fn (Category $category): array => [
                'slug' => $category->slug,
                'name' => $category->name,
                'href' => route('categories.show', $category->slug, false),
            ])->all());
    }
}
