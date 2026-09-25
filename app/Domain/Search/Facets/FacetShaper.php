<?php

namespace App\Domain\Search\Facets;

use App\Domain\Search\Local\SearchableEntry;
use App\Domain\Search\Local\SearchableType;

/**
 * Type-tab counts and product facet counts over one result set. A product
 * counts once per facet value (a duplicated ingredient or category slug is
 * not double counted). Values are ordered by count descending, then value
 * ascending, so the output is deterministic.
 */
final readonly class FacetShaper
{
    /**
     * @param  list<SearchableEntry>  $entries
     */
    public function shape(array $entries): SearchFacets
    {
        $byType = [];
        $brands = [];
        $categories = [];
        $ingredients = [];

        foreach ($entries as $entry) {
            $byType[$entry->type->value] = ($byType[$entry->type->value] ?? 0) + 1;

            if ($entry->type !== SearchableType::Product) {
                continue;
            }

            $attributes = $entry->attributes;

            if ($attributes->brandSlug !== null) {
                $brands[$attributes->brandSlug] = ($brands[$attributes->brandSlug] ?? 0) + 1;
            }

            foreach (array_unique($attributes->categoryPath) as $slug) {
                $categories[$slug] = ($categories[$slug] ?? 0) + 1;
            }

            foreach (array_unique($attributes->ingredientSlugs) as $slug) {
                $ingredients[$slug] = ($ingredients[$slug] ?? 0) + 1;
            }
        }

        $typeCounts = [
            'all' => count($entries),
            'product' => $byType[SearchableType::Product->value] ?? 0,
            'brand' => $byType[SearchableType::Brand->value] ?? 0,
            'shop' => $byType[SearchableType::Shop->value] ?? 0,
            'category' => $byType[SearchableType::Category->value] ?? 0,
            'ingredient' => $byType[SearchableType::Ingredient->value] ?? 0,
        ];

        return new SearchFacets($typeCounts, self::values($brands), self::values($categories), self::values($ingredients));
    }

    /**
     * @param  array<array-key, int>  $counts
     * @return list<FacetValue>
     */
    private static function values(array $counts): array
    {
        $values = [];

        foreach ($counts as $value => $count) {
            $values[] = new FacetValue((string) $value, $count);
        }

        usort($values, static fn (FacetValue $a, FacetValue $b): int => ($b->count <=> $a->count) ?: strcmp($a->value, $b->value));

        return $values;
    }
}
