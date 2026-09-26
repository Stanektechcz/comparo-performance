<?php

namespace App\Domain\Search\Engines;

use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Query\SearchFilters;
use App\Domain\Search\Query\SortOption;
use InvalidArgumentException;

/**
 * Meilisearch filter and sort expressions, built ONLY from allow-listed,
 * typed values: market codes (/^[A-Z]{2}$/), slugs (/^[a-z0-9]+(-[a-z0-9]+)*$/),
 * integers and bounded floats. Attribute names are constants of this class.
 * Anything else throws before a request is made, so user input can never be
 * concatenated into a filter.
 *
 * Returned lists are AND-ed by Meilisearch (array filter syntax).
 */
final class MeilisearchFilters
{
    private const string MARKET = '/^[A-Z]{2}$/';

    private const string SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const int MAX_SLUG_LENGTH = 100;

    /** Document compliance values that keep a product visible (A-21). */
    private const array VISIBLE_COMPLIANCE = ['allowed', 'restricted', 'unknown'];

    /**
     * Visibility plus (for products) the typed filters of the query.
     *
     * @return list<non-empty-string>
     */
    public static function for(SearchIndex $index, string $market, SearchFilters $filters): array
    {
        return match ($index) {
            SearchIndex::Products => [...self::productVisibility($market), ...self::productFilters($filters, $market)],
            SearchIndex::Merchants => self::merchantVisibility($market),
            SearchIndex::Brands, SearchIndex::Categories, SearchIndex::Ingredients => [],
        };
    }

    /**
     * A product is visible when it has data for the market and is not blocked there.
     *
     * @return list<non-empty-string>
     */
    public static function productVisibility(string $market): array
    {
        $market = self::market($market);

        return [
            "markets.{$market}.compliance IN [".self::quoteAll(self::VISIBLE_COMPLIANCE).']',
            "NOT blocked_markets = \"{$market}\"",
        ];
    }

    /**
     * Merchants are visible only in markets they ship to (A-28).
     *
     * @return list<non-empty-string>
     */
    public static function merchantVisibility(string $market): array
    {
        return ['shipping_markets = "'.self::market($market).'"'];
    }

    /**
     * Price bounds are market-currency minor units and compare
     * `markets.{CC}.min_total_market_minor` (the lowest total converted into
     * the market currency), never the offer-currency display total.
     *
     * @return list<non-empty-string>
     */
    public static function productFilters(SearchFilters $filters, string $market): array
    {
        $market = self::market($market);
        $clauses = [];

        if ($filters->brandSlugs !== []) {
            $clauses[] = 'brand.slug IN ['.self::quoteAll(self::slugs($filters->brandSlugs)).']';
        }

        if ($filters->categorySlugs !== []) {
            $clauses[] = 'category.path IN ['.self::quoteAll(self::slugs($filters->categorySlugs)).']';
        }

        if ($filters->ingredientSlugs !== []) {
            $clauses[] = 'ingredients IN ['.self::quoteAll(self::slugs($filters->ingredientSlugs)).']';
        }

        if ($filters->inStock) {
            $clauses[] = "markets.{$market}.in_stock = true";
        }

        if ($filters->minRating !== null) {
            $clauses[] = 'rating.average >= '.self::rating($filters->minRating);
        }

        if ($filters->priceMinMinor !== null) {
            $clauses[] = "markets.{$market}.min_total_market_minor >= ".self::nonNegative($filters->priceMinMinor);
        }

        if ($filters->priceMaxMinor !== null) {
            $clauses[] = "markets.{$market}.min_total_market_minor <= ".self::nonNegative($filters->priceMaxMinor);
        }

        return $clauses;
    }

    /**
     * Sort rules per index; relevance (and orders an index cannot sort by,
     * e.g. price for brands) leaves Meilisearch's ranking rules in charge.
     *
     * @return list<string>
     */
    public static function sort(SearchIndex $index, SortOption $sort, string $market): array
    {
        $market = self::market($market);

        return match ($sort) {
            SortOption::Relevance => [],
            SortOption::PriceAsc => $index === SearchIndex::Products ? ["markets.{$market}.min_total_eur_minor:asc"] : [],
            SortOption::Rating => in_array($index, [SearchIndex::Products, SearchIndex::Merchants], true) ? ['rating.average:desc', 'rating.count:desc'] : [],
            SortOption::Name => ['name:asc'],
        };
    }

    public static function market(string $market): string
    {
        if (preg_match(self::MARKET, $market) !== 1) {
            throw new InvalidArgumentException('Invalid market code for a search filter.');
        }

        return $market;
    }

    /**
     * @param  list<string>  $slugs
     * @return list<string>
     */
    private static function slugs(array $slugs): array
    {
        foreach ($slugs as $slug) {
            if (strlen($slug) > self::MAX_SLUG_LENGTH || preg_match(self::SLUG, $slug) !== 1) {
                throw new InvalidArgumentException('Invalid slug for a search filter.');
            }
        }

        return $slugs;
    }

    /**
     * Values are allow-listed before quoting, so they contain no quote or backslash.
     *
     * @param  list<string>  $values
     */
    private static function quoteAll(array $values): string
    {
        return implode(', ', array_map(static fn (string $value): string => '"'.$value.'"', $values));
    }

    private static function rating(float $rating): string
    {
        if (is_nan($rating) || $rating < 0 || $rating > SearchFilters::MAX_RATING) {
            throw new InvalidArgumentException('Invalid rating for a search filter.');
        }

        return number_format($rating, 2, '.', '');
    }

    private static function nonNegative(int $value): string
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Invalid amount for a search filter.');
        }

        return (string) $value;
    }
}
