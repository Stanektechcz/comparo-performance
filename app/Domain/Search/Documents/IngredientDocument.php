<?php

namespace App\Domain\Search\Documents;

use App\Domain\Search\Contracts\SearchDocument;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Local\SearchableEntry;

/**
 * The `ingredients` index document: public name, slug and the number of
 * listed products declaring the ingredient on their label.
 */
final readonly class IngredientDocument implements IndexDocument
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public int $productCount,
        public string $indexedAt,
    ) {}

    public static function index(): SearchIndex
    {
        return SearchIndex::Ingredients;
    }

    public static function fromArray(array $payload): static
    {
        return new self(
            id: (int) $payload['id'],
            slug: (string) $payload['slug'],
            name: (string) $payload['name'],
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
            'type' => 'ingredient',
            'slug' => $this->slug,
            'name' => $this->name,
            'product_count' => $this->productCount,
            'indexed_at' => $this->indexedAt,
            'schema_version' => self::SCHEMA_VERSION,
        ];
    }

    public function toSearchableEntry(): SearchableEntry
    {
        return SearchableEntry::ingredient($this->id, $this->name);
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
