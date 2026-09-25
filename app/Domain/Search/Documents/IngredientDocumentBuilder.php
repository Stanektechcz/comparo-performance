<?php

namespace App\Domain\Search\Documents;

use App\Domain\Search\Contracts\SearchIndex;
use App\Models\Ingredient;
use DateTimeImmutable;

/**
 * The only factory of ingredient documents. Missing ingredients are returned
 * for deletion.
 */
final readonly class IngredientDocumentBuilder
{
    /**
     * @param  list<int>  $ingredientIds
     * @return BuiltDocuments<IngredientDocument>
     */
    public function build(array $ingredientIds, DateTimeImmutable $now): BuiltDocuments
    {
        $requested = BuildIds::normalize($ingredientIds);
        $indexedAt = BuildIds::indexedAt($now);
        $ingredients = Ingredient::query()
            ->whereKey($requested)
            ->withCount(['products' => static fn ($query) => $query->listed()->where('ingredient_product.is_listed', true)])
            ->orderBy('id')
            ->get();

        $documents = array_values(array_map(static fn (Ingredient $ingredient): IngredientDocument => new IngredientDocument(
            id: $ingredient->id,
            slug: $ingredient->slug,
            name: $ingredient->name,
            productCount: (int) $ingredient->getAttribute('products_count'),
            indexedAt: $indexedAt,
        ), $ingredients->all()));

        return new BuiltDocuments(
            SearchIndex::Ingredients,
            $documents,
            BuildIds::missing($requested, array_map(static fn (IngredientDocument $document): int => $document->id, $documents)),
        );
    }
}
