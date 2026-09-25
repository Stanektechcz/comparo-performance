<?php

namespace App\Domain\Search\Local;

use InvalidArgumentException;

/**
 * Filterable and sortable facts of a searchable entry. Only products carry
 * brand, category, ingredient and market data; every type may carry a rating.
 */
final readonly class EntryAttributes
{
    /**
     * @param  list<string>  $categoryPath  category slugs from the root to the product's category
     * @param  list<string>  $ingredientSlugs
     * @param  array<string, MarketAttributes>  $markets  ISO-3166 alpha-2 => state (active markets only)
     */
    public function __construct(
        public ?string $brandSlug = null,
        public array $categoryPath = [],
        public array $ingredientSlugs = [],
        public array $markets = [],
        public ?float $ratingAverage = null,
        public int $ratingCount = 0,
    ) {
        foreach (array_keys($markets) as $market) {
            if (preg_match('/^[A-Z]{2}$/', $market) !== 1) {
                throw new InvalidArgumentException("Invalid market code [{$market}].");
            }
        }

        if ($ratingAverage !== null && ($ratingAverage < 0 || $ratingAverage > 5)) {
            throw new InvalidArgumentException('A rating average lies between 0 and 5.');
        }

        if ($ratingCount < 0) {
            throw new InvalidArgumentException('A rating count cannot be negative.');
        }
    }

    public function market(string $market): ?MarketAttributes
    {
        return $this->markets[$market] ?? null;
    }
}
