<?php

namespace App\Domain\Search\Documents;

use App\Domain\Search\Contracts\SearchDocument;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Local\SearchableEntry;

/**
 * The `brands` index document: public name, slug, approved/suggested
 * aliases and the number of listed products.
 */
final readonly class BrandDocument implements IndexDocument
{
    /**
     * @param  list<string>  $aliases
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public array $aliases,
        public int $productCount,
        public string $indexedAt,
    ) {}

    public static function index(): SearchIndex
    {
        return SearchIndex::Brands;
    }

    public static function fromArray(array $payload): static
    {
        return new self(
            id: (int) $payload['id'],
            slug: (string) $payload['slug'],
            name: (string) $payload['name'],
            aliases: Payload::strings($payload, 'aliases'),
            productCount: (int) ($payload['product_count'] ?? 0),
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
            'type' => 'brand',
            'slug' => $this->slug,
            'name' => $this->name,
            'aliases' => $this->aliases,
            'product_count' => $this->productCount,
            'indexed_at' => $this->indexedAt,
            'schema_version' => self::SCHEMA_VERSION,
        ];
    }

    public function toSearchableEntry(): SearchableEntry
    {
        return SearchableEntry::brand($this->id, $this->name);
    }

    public function searchableText(): string
    {
        return Payload::searchableText([$this->name, ...$this->aliases]);
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument($this->id(), self::index()->entityType(), $this->toArray(), $this->searchableText(), self::SCHEMA_VERSION);
    }
}
