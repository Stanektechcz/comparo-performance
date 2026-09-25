<?php

namespace App\Domain\Search\Documents;

use App\Domain\Search\Contracts\SearchIndex;

/**
 * Rebuilds typed documents from stored payloads (database engine).
 */
final class DocumentHydrator
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(SearchIndex $index, array $payload): IndexDocument
    {
        return match ($index) {
            SearchIndex::Products => ProductDocument::fromArray($payload),
            SearchIndex::Brands => BrandDocument::fromArray($payload),
            SearchIndex::Merchants => MerchantDocument::fromArray($payload),
            SearchIndex::Categories => CategoryDocument::fromArray($payload),
            SearchIndex::Ingredients => IngredientDocument::fromArray($payload),
        };
    }
}
