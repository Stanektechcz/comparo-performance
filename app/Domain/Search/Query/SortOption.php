<?php

namespace App\Domain\Search\Query;

/**
 * Result orderings offered on the search page. Relevance keeps the
 * prototype's stable order; every other option breaks ties by relevance
 * score, then rating count (descending), name and id.
 */
enum SortOption: string
{
    case Relevance = 'relevance';
    case PriceAsc = 'price_asc';
    case Rating = 'rating';
    case Name = 'name';
}
