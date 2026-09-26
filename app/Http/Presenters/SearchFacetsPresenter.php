<?php

namespace App\Http\Presenters;

use App\Domain\Search\Contracts\SpellingSuggestion;
use App\Domain\Search\Facets\FacetValue;
use App\Domain\Search\Facets\SearchFacets;
use App\Domain\Search\Queries\SearchPageLookups;
use App\Models\Category;

/**
 * Formats the navigation around search results: type tabs with counts,
 * facet options with display names, did-you-mean links and the categories
 * offered when nothing is found. Names are loaded by SearchPageLookups.
 *
 * @phpstan-import-type Criteria from SearchPresenter
 *
 * @phpstan-type FacetOptionProps array{value: string, label: string, count: int, selected: bool}
 */
final readonly class SearchFacetsPresenter
{
    public const int BROWSE_CATEGORIES = 8;

    public const int DID_YOU_MEAN = 3;

    public const array NO_COUNTS = ['all' => 0, 'product' => 0, 'brand' => 0, 'shop' => 0, 'category' => 0, 'ingredient' => 0];

    private const array TAB_LABELS = [
        'all' => 'All',
        'product' => 'Products',
        'brand' => 'Brands',
        'shop' => 'Shops',
        'category' => 'Categories',
        'ingredient' => 'Ingredients',
    ];

    public function __construct(private SearchPageLookups $lookups) {}

    /**
     * @param  array<string, int>  $counts
     * @return list<array{key: string, label: string, count: int}>
     */
    public function tabs(array $counts): array
    {
        $tabs = [];
        foreach (self::TAB_LABELS as $key => $label) {
            $tabs[] = ['key' => $key, 'label' => $label, 'count' => (int) ($counts[$key] ?? 0)];
        }

        return $tabs;
    }

    /**
     * Facet options with display names; selected values missing from the
     * result facets are kept (count 0) so they can be unselected.
     *
     * @param  Criteria  $criteria
     * @return array{brands: list<FacetOptionProps>, categories: list<FacetOptionProps>, ingredients: list<FacetOptionProps>}
     */
    public function facets(SearchFacets $facets, array $criteria): array
    {
        return [
            'brands' => $this->options('brand', $facets->brands, $criteria['brand']),
            'categories' => $this->options('category', $facets->categories, $criteria['category']),
            'ingredients' => $this->options('ingredient', $facets->ingredients, $criteria['ingredient']),
        ];
    }

    /**
     * @param  list<SpellingSuggestion>  $suggestions
     * @return list<array{label: string, href: string}>
     */
    public function didYouMean(array $suggestions): array
    {
        $labels = array_slice(array_values(array_unique(array_map(static fn (SpellingSuggestion $suggestion): string => $suggestion->label, $suggestions))), 0, self::DID_YOU_MEAN);

        return array_map(static fn (string $label): array => ['label' => $label, 'href' => route('search', ['q' => $label], false)], $labels);
    }

    /**
     * @return list<array{slug: string, name: string, href: string}>
     */
    public function browseCategories(): array
    {
        return array_map(static fn (Category $category): array => [
            'slug' => $category->slug,
            'name' => $category->name,
            'href' => route('categories.show', $category->slug, false),
        ], $this->lookups->topLevelCategories(self::BROWSE_CATEGORIES));
    }

    /**
     * @param  'brand'|'category'|'ingredient'  $facet
     * @param  list<FacetValue>  $values
     * @param  list<string>  $selected
     * @return list<FacetOptionProps>
     */
    private function options(string $facet, array $values, array $selected): array
    {
        $counts = [];
        foreach ($values as $value) {
            $counts[$value->value] = $value->count;
        }

        foreach ($selected as $slug) {
            $counts[$slug] ??= 0;
        }

        if ($counts === []) {
            return [];
        }

        $labels = $this->lookups->facetNames($facet, array_map(strval(...), array_keys($counts)));
        $options = [];

        foreach ($counts as $slug => $count) {
            $slug = (string) $slug;
            $options[] = ['value' => $slug, 'label' => $labels[$slug] ?? $slug, 'count' => $count, 'selected' => in_array($slug, $selected, true)];
        }

        return $options;
    }
}
