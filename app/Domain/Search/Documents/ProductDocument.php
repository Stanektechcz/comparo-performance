<?php

namespace App\Domain\Search\Documents;

use App\Domain\Search\Contracts\SearchDocument;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Local\EntryAttributes;
use App\Domain\Search\Local\SearchableEntry;
use InvalidArgumentException;

/**
 * The `products` index document (docs/architecture/phase-3-search.md §2):
 * public catalogue fields plus a per-market map for the active markets only.
 * Displayed prices always come from the live comparison, never from here.
 */
final readonly class ProductDocument implements IndexDocument
{
    public const int SHORT_DESCRIPTION_LIMIT = 300;

    /**
     * @param  list<string>  $variantNames
     * @param  list<string>  $identifiers  product EAN, SKU and variant EANs
     * @param  list<string>  $brandAliases  non-rejected brand aliases
     * @param  list<string>  $categoryPath  category slugs from the root to the product category
     * @param  list<string>  $ingredientNames  listed (label) ingredients in label order
     * @param  list<string>  $ingredientSlugs  the same ingredients' slugs
     * @param  array<string, ProductMarketEntry>  $markets  active market code => state
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public string $packLabel,
        public ?string $shortDescription,
        public array $variantNames,
        public array $identifiers,
        public ?string $ean,
        public ?string $sku,
        public int $brandId,
        public string $brandSlug,
        public string $brandName,
        public array $brandAliases,
        public int $categoryId,
        public string $categorySlug,
        public string $categoryName,
        public array $categoryPath,
        public array $ingredientNames,
        public array $ingredientSlugs,
        public ?float $ratingAverage,
        public int $ratingCount,
        public array $markets,
        public string $indexedAt,
    ) {
        if ($shortDescription !== null && mb_strlen($shortDescription) > self::SHORT_DESCRIPTION_LIMIT) {
            throw new InvalidArgumentException('The short description is limited to '.self::SHORT_DESCRIPTION_LIMIT.' characters.');
        }

        if (count($ingredientNames) !== count($ingredientSlugs)) {
            throw new InvalidArgumentException('Every listed ingredient needs its slug.');
        }

        foreach (array_keys($markets) as $market) {
            if (preg_match('/^[A-Z]{2}$/', $market) !== 1) {
                throw new InvalidArgumentException("Invalid market code [{$market}].");
            }
        }
    }

    public static function index(): SearchIndex
    {
        return SearchIndex::Products;
    }

    public static function fromArray(array $payload): static
    {
        $brand = Payload::map($payload, 'brand');
        $category = Payload::map($payload, 'category');
        $rating = Payload::map($payload, 'rating');
        $markets = [];

        foreach (Payload::map($payload, 'markets') as $code => $entry) {
            $markets[(string) $code] = ProductMarketEntry::fromArray(is_array($entry) ? $entry : []);
        }

        return new self(
            id: (int) $payload['id'],
            slug: (string) $payload['slug'],
            name: (string) $payload['name'],
            packLabel: (string) ($payload['pack_label'] ?? ''),
            shortDescription: Payload::optionalString($payload, 'short_description'),
            variantNames: Payload::strings($payload, 'variant_names'),
            identifiers: Payload::strings($payload, 'identifiers'),
            ean: Payload::optionalString($payload, 'ean'),
            sku: Payload::optionalString($payload, 'sku'),
            brandId: (int) $brand['id'],
            brandSlug: (string) $brand['slug'],
            brandName: (string) $brand['name'],
            brandAliases: Payload::strings($payload, 'brand_aliases'),
            categoryId: (int) $category['id'],
            categorySlug: (string) $category['slug'],
            categoryName: (string) $category['name'],
            categoryPath: Payload::strings($category, 'path'),
            ingredientNames: Payload::strings($payload, 'ingredient_names'),
            ingredientSlugs: Payload::strings($payload, 'ingredients'),
            ratingAverage: Payload::optionalFloat($rating, 'average'),
            ratingCount: (int) ($rating['count'] ?? 0),
            markets: $markets,
            indexedAt: (string) $payload['indexed_at'],
        );
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => 'product',
            'slug' => $this->slug,
            'name' => $this->name,
            'pack_label' => $this->packLabel,
            'short_description' => $this->shortDescription,
            'variant_names' => $this->variantNames,
            'identifiers' => $this->identifiers,
            'ean' => $this->ean,
            'sku' => $this->sku,
            'brand' => ['id' => $this->brandId, 'slug' => $this->brandSlug, 'name' => $this->brandName],
            'brand_aliases' => $this->brandAliases,
            'category' => ['id' => $this->categoryId, 'slug' => $this->categorySlug, 'name' => $this->categoryName, 'path' => $this->categoryPath],
            'ingredient_names' => $this->ingredientNames,
            'ingredients' => $this->ingredientSlugs,
            'rating' => ['average' => $this->ratingAverage, 'count' => $this->ratingCount],
            'markets' => array_map(static fn (ProductMarketEntry $entry): array => $entry->toArray(), $this->markets),
            'blocked_markets' => $this->marketsWhere(static fn (ProductMarketEntry $entry): bool => $entry->compliance->isBlocked()),
            'purchasable_markets' => $this->marketsWhere(static fn (ProductMarketEntry $entry): bool => $entry->purchasable),
            'offer_markets' => $this->marketsWhere(static fn (ProductMarketEntry $entry): bool => $entry->offerCount > 0),
            'unknown_markets' => $this->marketsWhere(static fn (ProductMarketEntry $entry): bool => $entry->compliance === DocumentCompliance::Unknown),
            'indexed_at' => $this->indexedAt,
            'schema_version' => self::SCHEMA_VERSION,
        ];
    }

    public function toSearchableEntry(): SearchableEntry
    {
        return SearchableEntry::product(
            id: $this->id,
            name: $this->name,
            brandName: $this->brandName,
            ingredientNames: $this->ingredientNames,
            categoryName: $this->categoryName,
            sku: $this->sku,
            ean: $this->ean,
            attributes: new EntryAttributes(
                brandSlug: $this->brandSlug,
                categoryPath: $this->categoryPath,
                ingredientSlugs: $this->ingredientSlugs,
                markets: array_map(static fn (ProductMarketEntry $entry) => $entry->toMarketAttributes(), $this->markets),
                ratingAverage: $this->ratingAverage,
                ratingCount: $this->ratingCount,
            ),
        );
    }

    public function searchableText(): string
    {
        return Payload::searchableText([
            $this->name,
            $this->brandName,
            ...$this->brandAliases,
            ...$this->ingredientNames,
            $this->categoryName,
            ...$this->variantNames,
            ...$this->identifiers,
        ]);
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument($this->id(), self::index()->entityType(), $this->toArray(), $this->searchableText(), self::SCHEMA_VERSION);
    }

    /**
     * @param  callable(ProductMarketEntry): bool  $predicate
     * @return list<string>
     */
    private function marketsWhere(callable $predicate): array
    {
        return array_map(strval(...), array_keys(array_filter($this->markets, $predicate)));
    }
}
