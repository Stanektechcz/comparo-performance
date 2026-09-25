<?php

namespace App\Domain\Search\Settings;

use App\Domain\Platform\Markets\MarketResolver;
use App\Domain\Search\Contracts\SearchIndex;
use App\Models\SearchSynonym;

/**
 * Builds the code-defined settings with their data inputs: the active
 * markets (MarketResolver) and the active synonym groups (search_synonyms).
 */
final readonly class IndexSettingsFactory
{
    public function __construct(private MarketResolver $markets) {}

    public function for(SearchIndex $index): IndexSettings
    {
        $synonyms = new SynonymMap(self::synonymGroups());

        return match ($index) {
            SearchIndex::Products => new ProductIndexSettings(array_map(strval(...), array_keys($this->markets->all())), $synonyms),
            SearchIndex::Brands => new BrandIndexSettings($synonyms),
            SearchIndex::Merchants => new MerchantIndexSettings($synonyms),
            SearchIndex::Categories => new CategoryIndexSettings($synonyms),
            SearchIndex::Ingredients => new IngredientIndexSettings($synonyms),
        };
    }

    /**
     * @return list<IndexSettings> in SearchIndex order
     */
    public function all(): array
    {
        return array_map($this->for(...), SearchIndex::cases());
    }

    /**
     * Active terms grouped by `group_key`; groups and terms keep id
     * (insertion) order, so the seeded prototype groups keep `H.synonyms`
     * order.
     *
     * @return array<string, list<string>>
     */
    public static function synonymGroups(): array
    {
        $groups = [];

        foreach (SearchSynonym::query()->active()->orderBy('id')->get(['id', 'group_key', 'term']) as $synonym) {
            $groups[$synonym->group_key][] = $synonym->term;
        }

        return $groups;
    }
}
