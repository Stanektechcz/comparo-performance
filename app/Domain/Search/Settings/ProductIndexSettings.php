<?php

namespace App\Domain\Search\Settings;

use App\Domain\Search\Contracts\SearchIndex;

/**
 * Settings of the `products` index (docs/architecture/phase-3-search.md §3).
 * Per-market attributes are declared explicitly for every active market
 * (`markets.DE.min_total_eur_minor`, …), so activating a country changes
 * the fingerprint and requires a settings sync plus a full reindex (A-22).
 */
final readonly class ProductIndexSettings implements IndexSettings
{
    public const int VERSION = 1;

    public const array SEARCHABLE = [
        'name',
        'brand.name',
        'brand_aliases',
        'ingredient_names',
        'category.name',
        'variant_names',
        'identifiers',
    ];

    public const array FILTERABLE = [
        'blocked_markets',
        'purchasable_markets',
        'offer_markets',
        'unknown_markets',
        'brand.slug',
        'category.path',
        'ingredients',
        'rating.average',
    ];

    /** Per active market: `markets.{CC}.{field}`. */
    public const array MARKET_FILTERABLE = ['compliance', 'in_stock', 'min_total_minor', 'min_total_eur_minor'];

    public const array SORTABLE = ['name', 'rating.average', 'rating.count'];

    public const array MARKET_SORTABLE = ['min_total_eur_minor'];

    /** Identifiers (EAN, SKU) must match exactly: no typos. */
    public const array TYPOS_DISABLED_ON = ['identifiers'];

    /** @var list<string> */
    public array $markets;

    private string $index;

    /**
     * @param  list<string>  $markets  active market codes
     */
    public function __construct(array $markets, public SynonymMap $synonyms, string $index = 'products')
    {
        $this->markets = SettingsSchema::markets($markets);
        $this->index = SettingsSchema::index($index, SearchIndex::Products);
    }

    public function index(): string
    {
        return $this->index;
    }

    public function forIndex(string $index): static
    {
        return new self($this->markets, $this->synonyms, $index);
    }

    public function version(): int
    {
        return self::VERSION;
    }

    public function toMeilisearch(): array
    {
        $filterable = self::FILTERABLE;
        $sortable = self::SORTABLE;

        foreach ($this->markets as $market) {
            foreach (self::MARKET_FILTERABLE as $field) {
                $filterable[] = "markets.{$market}.{$field}";
            }

            foreach (self::MARKET_SORTABLE as $field) {
                $sortable[] = "markets.{$market}.{$field}";
            }
        }

        return SettingsSchema::meilisearch(self::SEARCHABLE, $filterable, $sortable, $this->synonyms, self::TYPOS_DISABLED_ON);
    }

    public function fingerprint(): string
    {
        return SettingsSchema::fingerprint(SearchIndex::Products->value, self::VERSION, $this->toMeilisearch());
    }
}
