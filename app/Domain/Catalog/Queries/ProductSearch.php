<?php

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\ProductStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * The "choose a product" lookup shared by the staff and merchant matching
 * pickers: ACTIVE (public) products whose name or brand contains the term
 * (case-insensitive, LIKE wildcards escaped) or whose EAN equals it, ordered
 * by name then id, with their brand loaded.
 */
final class ProductSearch
{
    public const int DEFAULT_LIMIT = 20;

    /**
     * @return list<Product>
     */
    public function search(string $term, int $limit = self::DEFAULT_LIMIT): array
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
            ->limit(max(1, $limit))
            ->get(['id', 'brand_id', 'name', 'pack_label', 'ean'])
            ->all());
    }
}
