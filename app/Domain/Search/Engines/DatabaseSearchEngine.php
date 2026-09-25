<?php

namespace App\Domain\Search\Engines;

use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\Contracts\SearchHit;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Contracts\SearchResults;
use App\Domain\Search\Contracts\SpellingSuggestion;
use App\Domain\Search\Contracts\SuggestItem;
use App\Domain\Search\Contracts\SuggestResults;
use App\Domain\Search\DidYouMeanSuggestion;
use App\Domain\Search\Documents\DocumentHydrator;
use App\Domain\Search\Documents\IndexDocument;
use App\Domain\Search\Documents\MerchantDocument;
use App\Domain\Search\Local\LocalQueryEvaluator;
use App\Domain\Search\Local\MarketVisibility;
use App\Domain\Search\Local\SearchableEntry;
use App\Domain\Search\Local\SearchableType;
use App\Domain\Search\Query\SearchQuery;
use App\Domain\Search\Relevance\PrototypeRelevance;
use App\Domain\Search\Relevance\RelevanceHit;
use App\Domain\Search\Relevance\RelevanceWeights;
use App\Domain\Search\Relevance\SynonymExpansion;
use App\Domain\Search\Relevance\SynonymTable;
use App\Domain\Search\Settings\IndexSettings;
use App\Domain\Search\Settings\IndexSettingsFactory;
use App\Models\SearchDocument as StoredDocument;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The local/testing search engine (`scout.driver` collection, database or
 * null). Documents live in `search_documents`; every search loads the
 * documents of the five live indexes and evaluates them with the prototype
 * relevance (LocalQueryEvaluator), so ordering is prototype-exact.
 *
 * It scans: every query decodes all stored documents (fine for the demo
 * catalogue and tests, not for production volumes — production uses
 * Meilisearch). `searchable_text` is stored for inspection and a future
 * prefilter but is not used to narrow the scan, because prototype fuzzy
 * matching (Levenshtein, synonyms) cannot be expressed as a LIKE.
 *
 * Market visibility: products blocked in the market (or without data for
 * it) are dropped after scoring (A-21); merchants appear only in markets
 * listed in their `shipping_markets` (A-28). Did-you-mean runs over the
 * visible entries when nothing is visible.
 */
final readonly class DatabaseSearchEngine implements SearchEngine
{
    private const int WRITE_CHUNK = 200;

    public const int MAX_SUGGESTIONS = 20;

    public function __construct(private IndexNames $names) {}

    public function upsert(string $index, array $documents): void
    {
        $physical = $this->names->physical($index);
        $entity = SearchIndex::fromName($index)->entityType();
        $rows = [];

        foreach ($documents as $document) {
            if ($document->entityType !== $entity) {
                throw SearchEngineException::wrongIndex($document->id, $document->entityType->value, $index);
            }

            $rows[] = [
                'index_name' => $physical,
                'document_id' => $document->id,
                'entity_type' => $document->entityType->value,
                'payload' => json_encode($document->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
                'searchable_text' => $document->searchableText,
                'schema_version' => $document->schemaVersion,
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, self::WRITE_CHUNK) as $chunk) {
            StoredDocument::query()->upsert($chunk, ['index_name', 'document_id'], ['entity_type', 'payload', 'searchable_text', 'schema_version', 'updated_at']);
        }
    }

    public function delete(string $index, array $ids): void
    {
        $physical = $this->names->physical($index);

        foreach (array_chunk($ids, self::WRITE_CHUNK) as $chunk) {
            StoredDocument::query()->where('index_name', $physical)->whereIn('document_id', $chunk)->delete();
        }
    }

    public function search(SearchQuery $query): SearchResults
    {
        $documents = $this->liveDocuments();
        $entries = array_map(static fn (IndexDocument $document): SearchableEntry => $document->toSearchableEntry(), $documents);
        $result = $this->evaluator()->evaluate($query, $entries, self::visibility($documents));

        return new SearchResults(
            hits: array_map(static fn (RelevanceHit $hit): SearchHit => new SearchHit($hit->entry->type, $hit->entry->id, (float) $hit->score), $result->hits),
            total: $result->total,
            facets: $result->facets,
            page: $result->page,
            perPage: $result->perPage,
            didYouMean: array_map(static fn (DidYouMeanSuggestion $suggestion): SpellingSuggestion => new SpellingSuggestion(
                $suggestion->entry->type,
                $suggestion->entry->id,
                $suggestion->label(),
                (float) $suggestion->score,
            ), $result->didYouMean),
        );
    }

    public function suggest(string $prefix, string $market, int $limit): SuggestResults
    {
        if ($limit < 1 || $limit > self::MAX_SUGGESTIONS) {
            throw new InvalidArgumentException('Between 1 and '.self::MAX_SUGGESTIONS.' suggestions.');
        }

        $text = mb_substr($prefix, 0, SearchQuery::MAX_TEXT_LENGTH);
        $documents = $this->liveDocuments();
        $entries = array_map(static fn (IndexDocument $document): SearchableEntry => $document->toSearchableEntry(), $documents);
        $slugs = [];

        foreach ($documents as $position => $document) {
            $slugs[$entries[$position]->type->value.'|'.$entries[$position]->id] = $document->toArray()['slug'] ?? null;
        }

        $result = $this->evaluator()->evaluate(new SearchQuery($text, $market, perPage: $limit), $entries, self::visibility($documents));
        $items = [];

        foreach ($result->hits as $hit) {
            $slug = $slugs[$hit->entry->type->value.'|'.$hit->entry->id] ?? null;
            $items[] = new SuggestItem($hit->entry->type, $hit->entry->id, $hit->entry->name, $slug === null ? null : (string) $slug);
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

        $parking = '__swap__'.$first;

        DB::transaction(static function () use ($first, $second, $parking): void {
            StoredDocument::query()->where('index_name', $first)->update(['index_name' => $parking]);
            StoredDocument::query()->where('index_name', $second)->update(['index_name' => $first]);
            StoredDocument::query()->where('index_name', $parking)->update(['index_name' => $second]);
        });
    }

    /**
     * Settings are code-defined and the local engine needs none: they are
     * validated (index name, serialisable settings) and otherwise ignored.
     */
    public function applySettings(IndexSettings $settings): void
    {
        $this->names->physical($settings->index());
        json_encode($settings->toMeilisearch(), JSON_THROW_ON_ERROR);
    }

    public function flush(string $index): void
    {
        StoredDocument::query()->where('index_name', $this->names->physical($index))->delete();
    }

    /**
     * Relevance with the active synonym groups (seeded with the prototype
     * `H.synonyms`, so the default is prototype-exact).
     */
    private function evaluator(): LocalQueryEvaluator
    {
        return new LocalQueryEvaluator(new PrototypeRelevance(
            RelevanceWeights::prototype(),
            new SynonymExpansion(new SynonymTable(IndexSettingsFactory::synonymGroups())),
        ));
    }

    /**
     * The documents of the live indexes in prototype insertion order:
     * products, brands, shops, categories, ingredients, each by id.
     *
     * @return list<IndexDocument>
     */
    private function liveDocuments(): array
    {
        $documents = [];

        foreach (SearchIndex::cases() as $index) {
            $stored = StoredDocument::query()
                ->where('index_name', $this->names->live($index))
                ->get(['document_id', 'payload'])
                ->sortBy(static fn (StoredDocument $document): int => (int) $document->document_id)
                ->values();

            foreach ($stored as $document) {
                $documents[] = DocumentHydrator::fromPayload($index, $document->payload);
            }
        }

        return $documents;
    }

    /**
     * @param  list<IndexDocument>  $documents
     * @return Closure(SearchableEntry, string): bool
     */
    private static function visibility(array $documents): Closure
    {
        $shipping = [];

        foreach ($documents as $document) {
            if ($document instanceof MerchantDocument) {
                $shipping[$document->id] = array_fill_keys($document->shippingMarkets, true);
            }
        }

        $products = MarketVisibility::excludeBlocked();

        return static fn (SearchableEntry $entry, string $market): bool => $entry->type === SearchableType::Shop
            ? isset($shipping[$entry->id][$market])
            : $products($entry, $market);
    }
}
