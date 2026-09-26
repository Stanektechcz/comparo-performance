<?php

namespace App\Domain\Search\Settings;

use App\Domain\Search\Contracts\SearchIndex;

/**
 * Settings of the `categories` index.
 */
final readonly class CategoryIndexSettings implements IndexSettings
{
    public const int VERSION = 2;

    public const array SEARCHABLE = ['name'];

    public const array FILTERABLE = ['path'];

    public const array SORTABLE = ['name'];

    private string $index;

    public function __construct(public SynonymMap $synonyms, string $index = 'categories')
    {
        $this->index = SettingsSchema::index($index, SearchIndex::Categories);
    }

    public function index(): string
    {
        return $this->index;
    }

    public function forIndex(string $index): static
    {
        return new self($this->synonyms, $index);
    }

    public function version(): int
    {
        return self::VERSION;
    }

    public function toMeilisearch(): array
    {
        return SettingsSchema::meilisearch(self::SEARCHABLE, self::FILTERABLE, self::SORTABLE, $this->synonyms);
    }

    public function fingerprint(): string
    {
        return SettingsSchema::fingerprint(SearchIndex::Categories->value, self::VERSION, $this->toMeilisearch());
    }
}
