<?php

namespace App\Domain\Search\Local;

use App\Domain\Search\DidYouMean;
use App\Domain\Search\Facets\FacetShaper;
use App\Domain\Search\Query\SearchFilters;
use App\Domain\Search\Query\SearchQuery;
use App\Domain\Search\Query\SortOption;
use App\Domain\Search\Relevance\PrototypeRelevance;
use App\Domain\Search\Relevance\RelevanceHit;
use App\Domain\Shared\Text\TextFold;
use Closure;

/**
 * The local/test search pipeline over in-memory entries:
 * relevance → market visibility → typed filters → facets (over the filtered
 * set, before the type tab) → type tab → sort → page. Did-you-mean
 * suggestions are added when nothing at all matched in the market (the
 * prototype's zero-result gate).
 */
final readonly class LocalQueryEvaluator
{
    public function __construct(
        private PrototypeRelevance $relevance,
        private FacetShaper $facets = new FacetShaper,
        private DidYouMean $didYouMean = new DidYouMean,
    ) {}

    public static function prototype(): self
    {
        return new self(PrototypeRelevance::prototype());
    }

    /**
     * @param  list<SearchableEntry>  $entries  in prototype insertion order within each type
     * @param  ?Closure(SearchableEntry, string): bool  $visibleInMarket  defaults to MarketVisibility::excludeBlocked()
     */
    public function evaluate(SearchQuery $query, array $entries, ?Closure $visibleInMarket = null): LocalSearchResult
    {
        $visibleInMarket ??= MarketVisibility::excludeBlocked();
        $market = $query->market;

        $visible = array_values(array_filter(
            $this->relevance->rank($query->text, $entries),
            static fn (RelevanceHit $hit): bool => $visibleInMarket($hit->entry, $market),
        ));
        $filtered = array_values(array_filter(
            $visible,
            static fn (RelevanceHit $hit): bool => self::passes($hit->entry, $query->filters, $market),
        ));
        $facets = $this->facets->shape(array_map(static fn (RelevanceHit $hit): SearchableEntry => $hit->entry, $filtered));
        $selected = $query->type === null
            ? $filtered
            : array_values(array_filter($filtered, static fn (RelevanceHit $hit): bool => $hit->entry->type === $query->type));
        $sorted = self::sort($selected, $query->sort, $market);
        $normalized = $this->relevance->normalize($query->text);

        return new LocalSearchResult(
            query: $normalized,
            hits: array_slice($sorted, $query->offset(), $query->perPage),
            total: count($sorted),
            facets: $facets,
            page: $query->page,
            perPage: $query->perPage,
            didYouMean: $normalized->searchable && $visible === []
                ? $this->didYouMean->suggest($query->text, array_values(array_filter(
                    $entries,
                    static fn (SearchableEntry $entry): bool => $visibleInMarket($entry, $market),
                )))
                : [],
        );
    }

    /**
     * Typed filters constrain products only.
     */
    private static function passes(SearchableEntry $entry, SearchFilters $filters, string $market): bool
    {
        if ($entry->type !== SearchableType::Product || $filters->isEmpty()) {
            return true;
        }

        $attributes = $entry->attributes;
        $state = $attributes->market($market);

        return ($filters->brandSlugs === [] || in_array($attributes->brandSlug, $filters->brandSlugs, true))
            && ($filters->categorySlugs === [] || array_intersect($filters->categorySlugs, $attributes->categoryPath) !== [])
            && ($filters->ingredientSlugs === [] || array_intersect($filters->ingredientSlugs, $attributes->ingredientSlugs) !== [])
            && (! $filters->inStock || ($state !== null && $state->inStock))
            && ($filters->minRating === null || ($attributes->ratingAverage !== null && $attributes->ratingAverage >= $filters->minRating))
            && (! $filters->hasPriceRange() || self::inPriceRange($state?->minTotalMarketMinor, $filters));
    }

    private static function inPriceRange(?int $total, SearchFilters $filters): bool
    {
        return $total !== null
            && ($filters->priceMinMinor === null || $total >= $filters->priceMinMinor)
            && ($filters->priceMaxMinor === null || $total <= $filters->priceMaxMinor);
    }

    /**
     * Relevance keeps the relevance order (prototype ties). Other orders put
     * entries without the sort key last and break ties by relevance score,
     * rating count (descending), folded name, name and id.
     *
     * @param  list<RelevanceHit>  $hits
     * @return list<RelevanceHit>
     */
    private static function sort(array $hits, SortOption $sort, string $market): array
    {
        if ($sort === SortOption::Relevance) {
            return $hits;
        }

        usort($hits, static fn (RelevanceHit $a, RelevanceHit $b): int => self::compareKey($a, $b, $sort, $market) ?: self::tieBreak($a, $b));

        return $hits;
    }

    private static function compareKey(RelevanceHit $a, RelevanceHit $b, SortOption $sort, string $market): int
    {
        return match ($sort) {
            SortOption::PriceAsc => self::nullsLast(
                $a->entry->attributes->market($market)?->minTotalEurMinor,
                $b->entry->attributes->market($market)?->minTotalEurMinor,
                ascending: true,
            ),
            SortOption::Rating => self::nullsLast($a->entry->attributes->ratingAverage, $b->entry->attributes->ratingAverage, ascending: false),
            SortOption::Name => self::compareNames($a->entry, $b->entry),
            SortOption::Relevance => 0,
        };
    }

    private static function nullsLast(int|float|null $a, int|float|null $b, bool $ascending): int
    {
        if ($a === null || $b === null) {
            return ($a === null) <=> ($b === null);
        }

        return $ascending ? $a <=> $b : $b <=> $a;
    }

    private static function tieBreak(RelevanceHit $a, RelevanceHit $b): int
    {
        return ($b->score <=> $a->score)
            ?: ($b->entry->attributes->ratingCount <=> $a->entry->attributes->ratingCount)
            ?: self::compareNames($a->entry, $b->entry)
            ?: self::compareIds($a->entry->id, $b->entry->id)
            ?: ($a->position <=> $b->position);
    }

    private static function compareNames(SearchableEntry $a, SearchableEntry $b): int
    {
        return strcmp(TextFold::fold($a->name), TextFold::fold($b->name)) ?: strcmp($a->name, $b->name);
    }

    private static function compareIds(int|string $a, int|string $b): int
    {
        return is_int($a) && is_int($b) ? $a <=> $b : strcmp((string) $a, (string) $b);
    }
}
