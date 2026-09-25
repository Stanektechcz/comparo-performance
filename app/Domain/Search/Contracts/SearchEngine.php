<?php

namespace App\Domain\Search\Contracts;

use App\Domain\Search\Query\SearchQuery;
use App\Domain\Search\Settings\IndexSettings;

/**
 * The search engine port. Index names are logical (`products`,
 * `products_tmp`, …; see SearchIndex::NAME_PATTERN); adapters add the
 * configured prefix. Queries are typed (SearchQuery, SearchFilters): no
 * adapter accepts a raw filter string. Adapters are selected by
 * `scout.driver` (SearchServiceProvider).
 */
interface SearchEngine
{
    /**
     * Adds or replaces documents (by id) in an index. Synchronous: when it
     * returns the documents are searchable.
     *
     * @param  list<SearchDocument>  $documents
     */
    public function upsert(string $index, array $documents): void;

    /**
     * Removes documents by id; unknown ids are ignored.
     *
     * @param  list<string>  $ids
     */
    public function delete(string $index, array $ids): void;

    /**
     * Searches the live indexes in the query's market: products blocked there
     * and merchants that do not ship there are never returned or counted.
     */
    public function search(SearchQuery $query): SearchResults;

    /**
     * The best visible entries across all types for a typed prefix (no prices).
     */
    public function suggest(string $prefix, string $market, int $limit): SuggestResults;

    /**
     * Atomically exchanges the contents (and settings) of two indexes.
     */
    public function swap(string $a, string $b): void;

    /**
     * Applies index settings (searchable, filterable, sortable attributes,
     * typo tolerance, synonyms) and waits until they are in effect.
     */
    public function applySettings(IndexSettings $settings): void;

    /**
     * Removes an index and all its documents; a missing index is not an error.
     */
    public function flush(string $index): void;
}
