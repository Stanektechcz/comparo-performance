<?php

namespace App\Domain\Search;

use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\Contracts\SearchResults;
use App\Domain\Search\Contracts\SuggestResults;
use App\Domain\Search\Query\SearchQuery;

/**
 * The application entry point for searching (HTTP controllers use this, not
 * an engine). The engine is the one bound for `scout.driver`
 * (SearchServiceProvider). Results are references only: presenters load the
 * entities and re-check compliance before serialising (invariant 2).
 */
final readonly class SearchService
{
    public const int DEFAULT_SUGGESTIONS = 8;

    public function __construct(private SearchEngine $engine) {}

    public function search(SearchQuery $query): SearchResults
    {
        return $this->engine->search($query);
    }

    public function suggest(string $prefix, string $market, int $limit = self::DEFAULT_SUGGESTIONS): SuggestResults
    {
        return $this->engine->suggest($prefix, $market, $limit);
    }
}
