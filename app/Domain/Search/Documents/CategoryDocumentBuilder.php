<?php

namespace App\Domain\Search\Documents;

use App\Domain\Search\Contracts\SearchIndex;
use App\Models\Category;
use DateTimeImmutable;

/**
 * The only factory of category documents. Missing categories are returned
 * for deletion.
 */
final readonly class CategoryDocumentBuilder
{
    /**
     * @param  list<int>  $categoryIds
     * @return BuiltDocuments<CategoryDocument>
     */
    public function build(array $categoryIds, DateTimeImmutable $now): BuiltDocuments
    {
        $requested = BuildIds::normalize($categoryIds);
        $indexedAt = BuildIds::indexedAt($now);
        $categories = Category::query()
            ->whereKey($requested)
            ->withCount(['products' => static fn ($query) => $query->listed()])
            ->orderBy('id')
            ->get();
        $paths = $categories->isEmpty() ? [] : CategoryPaths::all();

        $documents = array_values(array_map(static fn (Category $category): CategoryDocument => new CategoryDocument(
            id: $category->id,
            slug: $category->slug,
            name: $category->name,
            path: $paths[$category->id] ?? [$category->slug],
            productCount: (int) $category->getAttribute('products_count'),
            indexedAt: $indexedAt,
        ), $categories->all()));

        return new BuiltDocuments(
            SearchIndex::Categories,
            $documents,
            BuildIds::missing($requested, array_map(static fn (CategoryDocument $document): int => $document->id, $documents)),
        );
    }
}
