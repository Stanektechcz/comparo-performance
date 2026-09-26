<?php

namespace App\Domain\Search\Settings;

use App\Domain\Search\Contracts\SearchIndex;

/**
 * Settings of the `ingredients` index.
 */
final readonly class IngredientIndexSettings implements IndexSettings
{
    public const int VERSION = 2;

    public const array SEARCHABLE = ['name'];

    public const array SORTABLE = ['name'];

    private string $index;

    public function __construct(public SynonymMap $synonyms, string $index = 'ingredients')
    {
        $this->index = SettingsSchema::index($index, SearchIndex::Ingredients);
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
        return SettingsSchema::meilisearch(self::SEARCHABLE, [], self::SORTABLE, $this->synonyms);
    }

    public function fingerprint(): string
    {
        return SettingsSchema::fingerprint(SearchIndex::Ingredients->value, self::VERSION, $this->toMeilisearch());
    }
}
