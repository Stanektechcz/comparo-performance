<?php

namespace App\Domain\Search\Facets;

/**
 * Counts for the type tabs and the product facets of one result set.
 */
final readonly class SearchFacets
{
    /**
     * @param  array{all: int, product: int, brand: int, shop: int, category: int, ingredient: int}  $typeCounts
     * @param  list<FacetValue>  $brands  brand slugs
     * @param  list<FacetValue>  $categories  category slugs (every level of each product's path)
     * @param  list<FacetValue>  $ingredients  ingredient slugs
     */
    public function __construct(
        public array $typeCounts,
        public array $brands,
        public array $categories,
        public array $ingredients,
    ) {}
}
