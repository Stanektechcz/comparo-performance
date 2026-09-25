<?php

namespace App\Domain\Search\Local;

use InvalidArgumentException;

/**
 * One searchable entity with the texts the relevance scores and the
 * attributes filters, facets and sorts read.
 *
 * Scored texts per type (DC `searchAll`):
 * - product: name, brand name + name, ingredient names joined, category name,
 *   exact SKU (folded) / EAN (raw);
 * - brand, category, ingredient: name;
 * - shop: name and web domain.
 */
final readonly class SearchableEntry
{
    /**
     * @param  list<string>  $ingredientNames  product ingredient names, in listing order
     */
    public function __construct(
        public SearchableType $type,
        public int|string $id,
        public string $name,
        public ?string $brandName = null,
        public array $ingredientNames = [],
        public ?string $categoryName = null,
        public ?string $sku = null,
        public ?string $ean = null,
        public ?string $web = null,
        public EntryAttributes $attributes = new EntryAttributes,
    ) {
        if ($type !== SearchableType::Product && ($brandName !== null || $ingredientNames !== [] || $categoryName !== null || $sku !== null || $ean !== null)) {
            throw new InvalidArgumentException('Only products carry brand, ingredient, category and identifier texts.');
        }

        if ($type !== SearchableType::Shop && $web !== null) {
            throw new InvalidArgumentException('Only shops carry a web domain.');
        }
    }

    /**
     * @param  list<string>  $ingredientNames
     */
    public static function product(
        int|string $id,
        string $name,
        ?string $brandName = null,
        array $ingredientNames = [],
        ?string $categoryName = null,
        ?string $sku = null,
        ?string $ean = null,
        EntryAttributes $attributes = new EntryAttributes,
    ): self {
        return new self(SearchableType::Product, $id, $name, $brandName, $ingredientNames, $categoryName, $sku, $ean, attributes: $attributes);
    }

    public static function brand(int|string $id, string $name, EntryAttributes $attributes = new EntryAttributes): self
    {
        return new self(SearchableType::Brand, $id, $name, attributes: $attributes);
    }

    public static function shop(int|string $id, string $name, ?string $web = null, EntryAttributes $attributes = new EntryAttributes): self
    {
        return new self(SearchableType::Shop, $id, $name, web: $web, attributes: $attributes);
    }

    public static function category(int|string $id, string $name, EntryAttributes $attributes = new EntryAttributes): self
    {
        return new self(SearchableType::Category, $id, $name, attributes: $attributes);
    }

    public static function ingredient(int|string $id, string $name, EntryAttributes $attributes = new EntryAttributes): self
    {
        return new self(SearchableType::Ingredient, $id, $name, attributes: $attributes);
    }
}
