<?php

namespace App\Domain\Search\Settings;

use App\Domain\Search\Contracts\SearchIndex;

/**
 * Settings of the `merchants` index. `shipping_markets` is filterable so a
 * market only sees merchants that ship there (A-28).
 */
final readonly class MerchantIndexSettings implements IndexSettings
{
    public const int VERSION = 1;

    public const array SEARCHABLE = ['name', 'website_host'];

    public const array FILTERABLE = ['shipping_markets', 'verified', 'rating.average'];

    public const array SORTABLE = ['name', 'rating.average', 'rating.count'];

    private string $index;

    public function __construct(public SynonymMap $synonyms, string $index = 'merchants')
    {
        $this->index = SettingsSchema::index($index, SearchIndex::Merchants);
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
        return SettingsSchema::fingerprint(SearchIndex::Merchants->value, self::VERSION, $this->toMeilisearch());
    }
}
