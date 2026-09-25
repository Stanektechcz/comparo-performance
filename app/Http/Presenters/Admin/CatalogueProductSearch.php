<?php

namespace App\Http\Presenters\Admin;

use App\Domain\Catalog\ProductStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * The staff "choose another product" picker: ACTIVE products whose name,
 * brand or EAN matches the term (case-insensitive), at most 20.
 *
 * Read-only catalogue lookup kept in the HTTP layer until a Catalog query
 * namespace exists (candidate for app/Domain/Catalog/Queries).
 */
final class CatalogueProductSearch
{
    public const int LIMIT = 20;

    /**
     * @return list<array{id: int, name: string, brand: string, pack: string, ean: ?string}>
     */
    public function search(string $term): array
    {
        $like = '%'.addcslashes(mb_strtolower($term), '\\%_').'%';

        return array_values(Product::query()
            ->with('brand:id,name')
            ->where('status', ProductStatus::Active)
            ->where(fn (Builder $query) => $query
                ->whereRaw("lower(products.name) like ? escape '\'", [$like])
                ->orWhere('ean', $term)
                ->orWhereHas('brand', fn (Builder $brand) => $brand->whereRaw("lower(brands.name) like ? escape '\'", [$like])))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get(['id', 'brand_id', 'name', 'pack_label', 'ean'])
            ->map(static fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'brand' => $product->brand->name,
                'pack' => $product->pack_label,
                'ean' => $product->ean,
            ])
            ->all());
    }
}
