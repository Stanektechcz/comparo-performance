<?php

namespace App\Http\Presenters;

use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Search\Contracts\SearchResults;
use App\Domain\Search\Facets\SearchFacets;
use App\Domain\Search\Query\SortOption;
use App\Http\Requests\Search\SearchRequest;
use DateTimeImmutable;

/**
 * Serializes the public search page (docs/architecture/phase-3-search.md §4)
 * by composing its parts: result cards (SearchResultCardsPresenter, which
 * formats only the hits VisibleSearchHits re-checked against the live
 * database — invariant 2), navigation (SearchFacetsPresenter), SEO and
 * pagination. Links are site-relative; no outbound link and no purchase URL
 * is ever serialized here.
 *
 * @phpstan-type Criteria array{q: string, type: string, sort: string, page: int, brand: list<string>, category: list<string>, ingredient: list<string>, price_min: ?int, price_max: ?int, in_stock: bool, min_rating: ?int}
 */
final readonly class SearchPresenter
{
    private const array SORT_LABELS = [
        'relevance' => 'Best match',
        'price_asc' => 'Lowest total price',
        'rating' => 'Highest rated',
        'name' => 'Name (A–Z)',
    ];

    public function __construct(
        private SearchResultCardsPresenter $cards,
        private SearchFacetsPresenter $navigation,
        private SeoPresenter $seo,
    ) {}

    /**
     * @param  Criteria  $criteria
     */
    public function present(SearchResults $results, array $criteria, MarketContext $market, DateTimeImmutable $now): SearchPresentation
    {
        ['cards' => $cards, 'refs' => $refs] = $this->cards->present($results->hits, $market, $now);
        $total = max(0, $results->total - (count($results->hits) - count($cards)));
        $lastPage = min(SearchRequest::MAX_PAGE, $results->lastPage());
        $didYouMean = $total === 0 ? $this->navigation->didYouMean($results->didYouMean) : [];

        return new SearchPresentation([
            ...$this->frame($criteria, $market, true),
            'tabs' => $this->navigation->tabs($results->facets->typeCounts),
            'results' => $cards,
            'facets' => $this->navigation->facets($results->facets, $criteria),
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
            'browseCategories' => $total === 0 && $didYouMean === [] ? $this->navigation->browseCategories() : [],
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
            'tabs' => $this->navigation->tabs(SearchFacetsPresenter::NO_COUNTS),
            'results' => [],
            'facets' => $this->navigation->facets(new SearchFacets(SearchFacetsPresenter::NO_COUNTS, [], [], []), $criteria),
            'pagination' => ['currentPage' => 1, 'lastPage' => 1, 'perPage' => SearchRequest::PER_PAGE, 'total' => 0, 'links' => ['prev' => null, 'next' => null]],
            'didYouMean' => [],
            'browseCategories' => $this->navigation->browseCategories(),
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
}
