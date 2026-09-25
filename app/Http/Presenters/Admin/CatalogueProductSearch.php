<?php

namespace App\Http\Presenters\Admin;

use App\Domain\Catalog\Queries\ProductSearch;
use App\Models\Product;

/**
 * The staff and merchant "choose a product" pickers: at most 20 ACTIVE
 * products found by {@see ProductSearch}, reduced to whitelisted fields.
 */
final class CatalogueProductSearch
{
    public const int LIMIT = 20;

    public function __construct(private readonly ProductSearch $products) {}

    /**
     * @return list<array{id: int, name: string, brand: string, pack: string, ean: ?string}>
     */
    public function search(string $term): array
    {
        return array_map(static fn (Product $product): array => [
            'id' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand->name,
            'pack' => $product->pack_label,
            'ean' => $product->ean,
        ], $this->products->search($term, self::LIMIT));
    }
}
