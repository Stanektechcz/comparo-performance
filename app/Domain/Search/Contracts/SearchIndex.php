<?php

namespace App\Domain\Search\Contracts;

use App\Domain\Search\Local\SearchableType;
use App\Domain\Search\SearchEntityType;
use InvalidArgumentException;

/**
 * The logical search indexes, declared in the prototype's result insertion
 * order (products, brands, shops, categories, ingredients). Engines add the
 * configured `scout.prefix` to build physical names; a reindex fills
 * `<index>_tmp` and swaps it in.
 */
enum SearchIndex: string
{
    case Products = 'products';
    case Brands = 'brands';
    case Merchants = 'merchants';
    case Categories = 'categories';
    case Ingredients = 'ingredients';

    public const string TEMPORARY_SUFFIX = '_tmp';

    /**
     * Logical index names accepted by engines: a live index or its reindex twin.
     */
    public const string NAME_PATTERN = '/^(products|brands|merchants|categories|ingredients)(_tmp)?$/';

    public static function forEntity(SearchEntityType $entity): self
    {
        return match ($entity) {
            SearchEntityType::Product => self::Products,
            SearchEntityType::Brand => self::Brands,
            SearchEntityType::Merchant => self::Merchants,
            SearchEntityType::Category => self::Categories,
            SearchEntityType::Ingredient => self::Ingredients,
        };
    }

    public static function forType(SearchableType $type): self
    {
        return match ($type) {
            SearchableType::Product => self::Products,
            SearchableType::Brand => self::Brands,
            SearchableType::Shop => self::Merchants,
            SearchableType::Category => self::Categories,
            SearchableType::Ingredient => self::Ingredients,
        };
    }

    public function entityType(): SearchEntityType
    {
        return match ($this) {
            self::Products => SearchEntityType::Product,
            self::Brands => SearchEntityType::Brand,
            self::Merchants => SearchEntityType::Merchant,
            self::Categories => SearchEntityType::Category,
            self::Ingredients => SearchEntityType::Ingredient,
        };
    }

    /**
     * The result type of this index's hits (merchants are "shop" results).
     */
    public function searchableType(): SearchableType
    {
        return match ($this) {
            self::Products => SearchableType::Product,
            self::Brands => SearchableType::Brand,
            self::Merchants => SearchableType::Shop,
            self::Categories => SearchableType::Category,
            self::Ingredients => SearchableType::Ingredient,
        };
    }

    public function temporary(): string
    {
        return $this->value.self::TEMPORARY_SUFFIX;
    }

    /**
     * The logical index a (possibly temporary) index name belongs to.
     */
    public static function fromName(string $name): self
    {
        if (preg_match(self::NAME_PATTERN, $name, $matches) !== 1) {
            throw new InvalidArgumentException("Unknown search index [{$name}].");
        }

        return self::from($matches[1]);
    }
}
