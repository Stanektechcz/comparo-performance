<?php

namespace App\Domain\Search\Queries;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Ingredient;

/**
 * Display data the search page needs besides its hits: the names behind
 * facet slugs and the top-level categories offered when nothing is found.
 * Public catalogue names only.
 */
final class SearchPageLookups
{
    /**
     * Names by slug for one facet; unknown slugs are simply missing.
     *
     * @param  'brand'|'category'|'ingredient'  $facet
     * @param  list<string>  $slugs
     * @return array<string, string>
     */
    public function facetNames(string $facet, array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        $query = match ($facet) {
            'brand' => Brand::query(),
            'category' => Category::query(),
            'ingredient' => Ingredient::query(),
        };

        /** @var array<string, string> $names */
        $names = $query->whereIn('slug', $slugs)->pluck('name', 'slug')->all();

        return $names;
    }

    /**
     * @return list<Category> top-level categories by name
     */
    public function topLevelCategories(int $limit): array
    {
        return array_values(Category::query()->whereNull('parent_id')->orderBy('name')->limit($limit)->get()->all());
    }
}
