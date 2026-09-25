<?php

namespace App\Domain\Platform\Cache;

use App\Models\Offer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Per-product version tokens. Bumping a token makes every market/currency
 * variant of that product's cached comparison unreachable at once, which works
 * on every cache store (no tags required).
 */
final class CatalogCacheVersion
{
    public function forProduct(int $productId): string
    {
        return (string) Cache::get(CacheKeys::productVersion($productId), '0');
    }

    public function bumpProduct(int $productId): void
    {
        Cache::forever(CacheKeys::productVersion($productId), (string) Str::ulid());
    }

    /**
     * A merchant-level change (coupon, shipping zone, trust input) affects every product it lists.
     */
    public function bumpMerchant(int $merchantId): void
    {
        Offer::query()
            ->where('merchant_id', $merchantId)
            ->distinct()
            ->pluck('product_id')
            ->each(fn (int $productId) => $this->bumpProduct($productId));
    }
}
