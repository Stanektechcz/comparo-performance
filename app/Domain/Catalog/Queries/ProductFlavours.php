<?php

namespace App\Domain\Catalog\Queries;

use App\Models\ProductVariant;

/**
 * Flavour variant names of several products in one query, in variant
 * position order.
 */
final class ProductFlavours
{
    /**
     * @param  list<int>  $productIds
     * @return array<int, list<string>> product id => flavour names (products without flavours are absent)
     */
    public function of(array $productIds): array
    {
        $productIds = array_values(array_unique($productIds));

        if ($productIds === []) {
            return [];
        }

        $flavours = [];

        foreach (ProductVariant::query()
            ->whereIn('product_id', $productIds)
            ->where('kind', ProductVariant::FLAVOUR)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['product_id', 'name']) as $variant) {
            $flavours[$variant->product_id][] = $variant->name;
        }

        return $flavours;
    }
}
