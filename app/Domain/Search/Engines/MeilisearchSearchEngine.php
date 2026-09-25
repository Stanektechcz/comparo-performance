<?php

namespace App\Domain\Search\Engines;

use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\Contracts\SearchHit;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Contracts\SearchResults;
use App\Domain\Search\Contracts\SuggestItem;
use App\Domain\Search\Contracts\SuggestResults;
use App\Domain\Search\Facets\FacetValue;
use App\Domain\Search\Facets\SearchFacets;
use App\Domain\Search\Query\QueryNormalizer;
use App\Domain\Search\Query\SearchFilters;
use App\Domain\Search\Query\SearchQuery;
use App\Domain\Search\Settings\IndexSettings;
use InvalidArgumentException;
use Meilisearch\Client;
use Meilisearch\Contracts\MultiSearchFederation;
use Meilisearch\Contracts\SearchQuery as MeilisearchQuery;

/**
 * The production search engine (`scout.driver` meilisearch). Uses the
 * Meilisearch client Scout configures (host and key from config, never
 * exposed to browsers). One index per entity type (`scout.prefix` +
 * products|brands|merchants|categories|ingredients).
 *
 * Every filter comes from MeilisearchFilters (allow-listed values only).
 * Products blocked in the market are excluded by filter, merchants must
 * list the market in `shipping_markets` (A-28). Relevance is Meilisearch's
 * (not prototype-exact, A-23); behaviour is held by the engine contract suite.
 *
 * A search is one multi-search (per-index totals + product facets, and the
 * page when a type tab is selected) plus, for the "all" tab, one federated
 * multi-search for the merged page. Did-you-mean is not provided by this
 * adapter (typo tolerance answers near misses); it returns an empty list.
 * Writes wait for their task, so a returned upsert is searchable.
 */
final readonly class MeilisearchSearchEngine implements SearchEngine
{
    public const string PRIMARY_KEY = 'id';

    public const int MAX_SUGGESTIONS = 20;

    private const int POLL_INTERVAL_MS = 50;

    private const array FACETS = ['brand.slug', 'category.path', 'ingredients'];

    public function __construct(
        private Client $client,
        private IndexNames $names,
        private int $taskTimeoutMs = 30_000,
        private QueryNormalizer $normalizer = new QueryNormalizer,
    ) {
        if ($taskTimeoutMs < 1) {
            throw new InvalidArgumentException('The task timeout must be positive.');
        }
    }

    public function upsert(string $index, array $documents): void
    {
        if ($documents === []) {
            return;
        }

        $physical = $this->names->physical($index);
        $entity = SearchIndex::fromName($index)->entityType();
        $payloads = [];

        foreach ($documents as $document) {
            if ($document->entityType !== $entity) {
                throw SearchEngineException::wrongIndex($document->id, $document->entityType->value, $index);
            }

            $payloads[] = $document->payload;
        }

        $this->wait($this->client->index($physical)->addDocuments($payloads, self::PRIMARY_KEY), 'upsert', $physical);
    }

    public function delete(string $index, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $physical = $this->names->physical($index);
        $this->wait($this->client->index($physical)->deleteDocuments($ids), 'delete', $physical, ignoreMissingIndex: true);
    }

    public function search(SearchQuery $query): SearchResults
    {
        $market = MeilisearchFilters::market($query->market);
        $normalized = $this->normalizer->normalize($query->text);

        if (! $normalized->searchable) {
            return new SearchResults([], 0, self::facets([], []), $query->page, $query->perPage);
        }

        $selected = $query->type === null ? null : SearchIndex::forType($query->type);
        $perPage = self::positive($query->perPage);
        $queries = [];

        foreach (SearchIndex::cases() as $index) {
            $search = $this->baseQuery($index, $normalized->trimmed, $market, $query->filters, [self::PRIMARY_KEY])
                ->setShowRankingScore(true)
                ->setPage($index === $selected ? $query->page : 1)
                ->setHitsPerPage($index === $selected ? $perPage : 0);

            if ($index === SearchIndex::Products) {
                $search->setFacets(self::FACETS);
            }

            $sort = $index === $selected ? MeilisearchFilters::sort($index, $query->sort, $market) : [];

            if ($sort !== []) {
                $search->setSort($sort);
            }

            $queries[] = $search;
        }

        $response = $this->client->multiSearch($queries);
        $results = array_values(is_array($response['results'] ?? null) ? $response['results'] : []);
        $totals = [];
        $byIndex = [];

        foreach (SearchIndex::cases() as $position => $index) {
            $result = is_array($results[$position] ?? null) ? $results[$position] : [];
            $byIndex[$index->value] = $result;
            $totals[$index->value] = (int) ($result['totalHits'] ?? $result['estimatedTotalHits'] ?? 0);
        }

        $facets = self::facets($totals, (array) ($byIndex[SearchIndex::Products->value]['facetDistribution'] ?? []));
        $hits = $selected === null
            ? $this->federatedPage($normalized->trimmed, $query, $market)
            : self::hits($selected, (array) ($byIndex[$selected->value]['hits'] ?? []));

        return new SearchResults(
            hits: $hits,
            total: $selected === null ? array_sum($totals) : $totals[$selected->value],
            facets: $facets,
            page: $query->page,
            perPage: $query->perPage,
        );
    }

    public function suggest(string $prefix, string $market, int $limit): SuggestResults
    {
        if ($limit < 1 || $limit > self::MAX_SUGGESTIONS) {
            throw new InvalidArgumentException('Between 1 and '.self::MAX_SUGGESTIONS.' suggestions.');
        }

        $market = MeilisearchFilters::market($market);
        $normalized = $this->normalizer->normalize(mb_substr($prefix, 0, SearchQuery::MAX_TEXT_LENGTH));

        if (! $normalized->searchable) {
            return new SuggestResults($prefix, $market, []);
        }

        $queries = array_map(fn (SearchIndex $index): MeilisearchQuery => $this->baseQuery($index, $normalized->trimmed, $market, new SearchFilters, [self::PRIMARY_KEY, 'name', 'slug']), SearchIndex::cases());

        $response = $this->client->multiSearch($queries, (new MultiSearchFederation)->setLimit(self::positive($limit))->setOffset(0));
        $items = [];

        foreach ((array) ($response['hits'] ?? []) as $hit) {
            $index = $this->names->liveIndexOf((string) ($hit['_federation']['indexUid'] ?? ''));

            if ($index !== null && isset($hit[self::PRIMARY_KEY], $hit['name'])) {
                $items[] = new SuggestItem($index->searchableType(), self::id($hit[self::PRIMARY_KEY]), (string) $hit['name'], isset($hit['slug']) ? (string) $hit['slug'] : null);
            }
        }

        return new SuggestResults($prefix, $market, $items);
    }

    public function swap(string $a, string $b): void
    {
        $first = $this->names->physical($a);
        $second = $this->names->physical($b);

        if (SearchIndex::fromName($a) !== SearchIndex::fromName($b) || $first === $second) {
            throw new InvalidArgumentException("Cannot swap [{$a}] with [{$b}].");
        }

        $this->ensureIndex($first);
        $this->ensureIndex($second);
        $this->wait($this->client->swapIndexes([[$first, $second]]), 'swap', "{$first}<->{$second}");
    }

    public function applySettings(IndexSettings $settings): void
    {
        $physical = $this->names->physical($settings->index());
        $this->ensureIndex($physical);
        $this->wait($this->client->index($physical)->updateSettings($settings->toMeilisearch()), 'settings', $physical);
    }

    public function flush(string $index): void
    {
        $physical = $this->names->physical($index);
        $this->wait($this->client->deleteIndex($physical), 'flush', $physical, ignoreMissingIndex: true);
    }

    /**
     * The "all" tab: one federated multi-search merges the five indexes by
     * ranking score (sorting across types is by relevance only).
     *
     * @return list<SearchHit>
     */
    private function federatedPage(string $text, SearchQuery $query, string $market): array
    {
        $queries = array_map(fn (SearchIndex $index): MeilisearchQuery => $this->baseQuery($index, $text, $market, $query->filters, [self::PRIMARY_KEY]), SearchIndex::cases());

        $response = $this->client->multiSearch($queries, (new MultiSearchFederation)->setLimit(self::positive($query->perPage))->setOffset(self::nonNegative($query->offset())));
        $hits = [];

        foreach ((array) ($response['hits'] ?? []) as $hit) {
            $index = $this->names->liveIndexOf((string) ($hit['_federation']['indexUid'] ?? ''));

            if ($index !== null && isset($hit[self::PRIMARY_KEY])) {
                $hits[] = new SearchHit($index->searchableType(), self::id($hit[self::PRIMARY_KEY]), (float) ($hit['_federation']['weightedRankingScore'] ?? 0));
            }
        }

        return $hits;
    }

    /**
     * @param  array<array-key, mixed>  $raw
     * @return list<SearchHit>
     */
    private static function hits(SearchIndex $index, array $raw): array
    {
        $hits = [];

        foreach ($raw as $hit) {
            if (is_array($hit) && isset($hit[self::PRIMARY_KEY])) {
                $hits[] = new SearchHit($index->searchableType(), self::id($hit[self::PRIMARY_KEY]), (float) ($hit['_rankingScore'] ?? 0));
            }
        }

        return $hits;
    }

    /**
     * @param  array<string, int>  $totals  index name => total hits
     * @param  array<array-key, mixed>  $distribution  facetDistribution of the products query
     */
    private static function facets(array $totals, array $distribution): SearchFacets
    {
        $typeCounts = ['all' => array_sum($totals)];

        foreach (SearchIndex::cases() as $index) {
            $typeCounts[$index->searchableType()->value] = $totals[$index->value] ?? 0;
        }

        /** @var array{all: int, product: int, brand: int, shop: int, category: int, ingredient: int} $typeCounts */
        return new SearchFacets(
            $typeCounts,
            self::facetValues((array) ($distribution['brand.slug'] ?? [])),
            self::facetValues((array) ($distribution['category.path'] ?? [])),
            self::facetValues((array) ($distribution['ingredients'] ?? [])),
        );
    }

    /**
     * Same order as FacetShaper: count descending, then value ascending.
     *
     * @param  array<array-key, mixed>  $counts
     * @return list<FacetValue>
     */
    private static function facetValues(array $counts): array
    {
        $values = [];

        foreach ($counts as $value => $count) {
            $values[] = new FacetValue((string) $value, (int) $count);
        }

        usort($values, static fn (FacetValue $a, FacetValue $b): int => ($b->count <=> $a->count) ?: strcmp($a->value, $b->value));

        return $values;
    }

    /**
     * @return positive-int
     */
    private static function positive(int $value): int
    {
        if ($value < 1) {
            throw new InvalidArgumentException("Expected a positive page size, got {$value}.");
        }

        return $value;
    }

    /**
     * @return int<0, max>
     */
    private static function nonNegative(int $value): int
    {
        if ($value < 0) {
            throw new InvalidArgumentException("Expected a non-negative offset, got {$value}.");
        }

        return $value;
    }

    private static function id(mixed $id): int|string
    {
        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : (string) $id;
    }

    /**
     * One index query with the market visibility and typed filters (no
     * `filter` key at all when there is nothing to filter).
     *
     * @param  list<string>  $attributes
     */
    private function baseQuery(SearchIndex $index, string $text, string $market, SearchFilters $filters, array $attributes): MeilisearchQuery
    {
        $search = (new MeilisearchQuery)
            ->setIndexUid($this->names->live($index))
            ->setQuery($text)
            ->setAttributesToRetrieve($attributes);
        $filter = MeilisearchFilters::for($index, $market, $filters);

        if ($filter !== []) {
            $search->setFilter($filter);
        }

        return $search;
    }

    private function ensureIndex(string $physical): void
    {
        $this->wait($this->client->createIndex($physical, ['primaryKey' => self::PRIMARY_KEY]), 'create', $physical, ignoreExistingIndex: true);
    }

    /**
     * @param  array<array-key, mixed>  $task  the enqueued task summary
     */
    private function wait(array $task, string $operation, string $index, bool $ignoreMissingIndex = false, bool $ignoreExistingIndex = false): void
    {
        $uid = $task['taskUid'] ?? $task['uid'] ?? null;

        if (! is_int($uid)) {
            throw new SearchEngineException("Search engine task [{$operation}] on [{$index}] returned no task id.");
        }

        $done = $this->client->waitForTask($uid, $this->taskTimeoutMs, self::POLL_INTERVAL_MS);

        if (($done['status'] ?? null) === 'succeeded') {
            return;
        }

        $code = (string) ($done['error']['code'] ?? 'unknown');

        if (($ignoreMissingIndex && $code === 'index_not_found') || ($ignoreExistingIndex && $code === 'index_already_exists')) {
            return;
        }

        throw SearchEngineException::taskFailed($operation, $index, $code, (string) ($done['error']['message'] ?? 'no message'));
    }
}
