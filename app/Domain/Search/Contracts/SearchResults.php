<?php

namespace App\Domain\Search\Contracts;

use App\Domain\Search\Facets\SearchFacets;

/**
 * One page of search results in the visitor's market: hit references of the
 * selected type tab, the tab total, the type-tab and product facet counts
 * (after market visibility and filters, before the type tab) and, only when
 * nothing at all was visible, did-you-mean suggestions.
 */
final readonly class SearchResults
{
    /**
     * @param  list<SearchHit>  $hits  the requested page in the requested order
     * @param  list<SpellingSuggestion>  $didYouMean
     */
    public function __construct(
        public array $hits,
        public int $total,
        public SearchFacets $facets,
        public int $page,
        public int $perPage,
        public array $didYouMean = [],
    ) {}

    public function lastPage(): int
    {
        return max(1, intdiv($this->total + $this->perPage - 1, $this->perPage));
    }

    public function isEmpty(): bool
    {
        return $this->total === 0;
    }
}
