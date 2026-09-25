<?php

namespace App\Domain\Search\Local;

use App\Domain\Search\DidYouMeanSuggestion;
use App\Domain\Search\Facets\SearchFacets;
use App\Domain\Search\Query\NormalizedQuery;
use App\Domain\Search\Relevance\RelevanceHit;

/**
 * One evaluated page of search results.
 */
final readonly class LocalSearchResult
{
    /**
     * @param  list<RelevanceHit>  $hits  the requested page, in the requested order
     * @param  int  $total  hits of the selected type tab after market visibility and filters
     * @param  list<DidYouMeanSuggestion>  $didYouMean  only when nothing matched in the market
     */
    public function __construct(
        public NormalizedQuery $query,
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
