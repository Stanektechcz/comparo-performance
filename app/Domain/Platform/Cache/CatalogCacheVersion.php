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
    /** Product ids read, and version tokens written, per batch of a merchant bump. */
    public const int MERCHANT_BATCH = 500;

    public function __construct(private readonly int $merchantBatch = self::MERCHANT_BATCH) {}

    public function forProduct(int $productId): string
    {
        return (string) Cache::get(CacheKeys::productVersion($productId), '0');
    }

    public function bumpProduct(int $productId): void
    {
        Cache::forever(CacheKeys::productVersion($productId), (string) Str::ulid());
    }

    /**
     * Bumps several products in one cache write (`putMany` without a TTL
     * stores forever).
     *
     * @param  list<int>  $productIds
     */
    public function bumpProducts(array $productIds): void
    {
        $tokens = [];

        foreach (array_unique($productIds) as $productId) {
            $tokens[CacheKeys::productVersion($productId)] = (string) Str::ulid();
        }

        if ($tokens !== []) {
            Cache::putMany($tokens);
        }
    }

    /**
     * A merchant-level change (coupon, shipping zone, trust input) affects
     * every product it lists. The distinct product ids are read in id-ordered
     * batches (keyset pagination, bounded memory) and each batch is written
     * with one cache call.
     */
    public function bumpMerchant(int $merchantId): void
    {
        $afterProductId = 0;

        do {
            /** @var list<int> $productIds */
            $productIds = Offer::query()
                ->where('merchant_id', $merchantId)
                ->where('product_id', '>', $afterProductId)
                ->distinct()
                ->orderBy('product_id')
                ->limit(max(1, $this->merchantBatch))
                ->pluck('product_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $this->bumpProducts($productIds);
            $afterProductId = $productIds === [] ? $afterProductId : $productIds[array_key_last($productIds)];
        } while (count($productIds) >= max(1, $this->merchantBatch));
    }
}
