<?php

namespace App\Domain\Search\Local;

/**
 * Searchable entity types, declared in the prototype's insertion order
 * (DC `searchAll` pushes products, brands, shops, categories, ingredients);
 * that order breaks relevance ties. Articles and coupons are not searchable
 * in Phase 3 (A-25).
 */
enum SearchableType: string
{
    case Product = 'product';
    case Brand = 'brand';
    case Shop = 'shop';
    case Category = 'category';
    case Ingredient = 'ingredient';

    /**
     * Position in the prototype's result insertion order.
     */
    public function insertionRank(): int
    {
        return match ($this) {
            self::Product => 0,
            self::Brand => 1,
            self::Shop => 2,
            self::Category => 3,
            self::Ingredient => 4,
        };
    }

    /**
     * Whether the did-you-mean pool includes this type (DC `sDidYouMean`:
     * canonical products, brands, shops, ingredient entities).
     */
    public function suggestsSpelling(): bool
    {
        return $this !== self::Category;
    }
}
