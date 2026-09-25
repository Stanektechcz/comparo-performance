<?php

namespace App\Domain\Search\Documents;

use App\Domain\Search\Contracts\SearchDocument;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Local\SearchableEntry;

/**
 * The `categories` index document: public name, slug, the slug path from the
 * root and the number of listed products filed directly under it.
 */
final readonly class CategoryDocument implements IndexDocument
{
    /**
     * @param  list<string>  $path  slugs from the root to this category (inclusive)
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public array $path,
        public int $productCount,
        public string $indexedAt,
    ) {}

    public static function index(): SearchIndex
    {
        return SearchIndex::Categories;
    }

    public static function fromArray(array $payload): static
    {
        return new self(
            id: (int) $payload['id'],
            slug: (string) $payload['slug'],
            name: (string) $payload['name'],
            path: Payload::strings($payload, 'path'),
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
            'type' => 'category',
            'slug' => $this->slug,
            'name' => $this->name,
            'path' => $this->path,
            'product_count' => $this->productCount,
            'indexed_at' => $this->indexedAt,
            'schema_version' => self::SCHEMA_VERSION,
        ];
    }

    public function toSearchableEntry(): SearchableEntry
    {
        return SearchableEntry::category($this->id, $this->name);
    }

    public function searchableText(): string
    {
        return Payload::searchableText([$this->name]);
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument($this->id(), self::index()->entityType(), $this->toArray(), $this->searchableText(), self::SCHEMA_VERSION);
    }
}
