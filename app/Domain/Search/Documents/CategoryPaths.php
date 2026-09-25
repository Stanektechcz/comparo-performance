<?php

namespace App\Domain\Search\Documents;

use App\Models\Category;

/**
 * Category slug paths from the root (the category tree is small; it is read
 * once per build).
 */
final class CategoryPaths
{
    /**
     * @return array<int, list<string>> category id => slugs from the root to the category
     */
    public static function all(): array
    {
        /** @var array<int, array{parent_id: ?int, slug: string}> $nodes */
        $nodes = Category::query()
            ->get(['id', 'parent_id', 'slug'])
            ->mapWithKeys(static fn (Category $category): array => [$category->id => ['parent_id' => $category->parent_id, 'slug' => $category->slug]])
            ->all();
        $paths = [];

        foreach (array_keys($nodes) as $id) {
            $path = [];
            $seen = [];
            $current = $id;

            // The seen-set guards against a (corrupt) parent cycle.
            while ($current !== null && isset($nodes[$current]) && ! isset($seen[$current])) {
                $seen[$current] = true;
                array_unshift($path, $nodes[$current]['slug']);
                $current = $nodes[$current]['parent_id'];
            }

            $paths[$id] = $path;
        }

        return $paths;
    }
}
