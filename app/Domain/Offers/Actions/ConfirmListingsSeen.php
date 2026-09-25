<?php

namespace App\Domain\Offers\Actions;

use App\Domain\Offers\ListingStatus;
use App\Domain\Platform\Cache\CatalogCacheVersion;
use App\Models\MerchantProduct;
use App\Models\Offer;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A feed run served exactly the same payload as the previous one (unchanged
 * checksum): every active listing of the source was seen again (A-11).
 *
 * Bulk, chunked: merchant_products.last_seen_at / last_seen_run_id move to
 * this run. When `comparo.feeds.unchanged_refreshes_freshness` is on, the
 * listings' active offers get last_feed_run_id and a forward-only
 * source_updated_at (the merchant re-confirmed the same prices), and the
 * cached comparison of every affected product is invalidated once, after
 * commit. updated_at is not touched: nothing about the listing changed.
 */
final class ConfirmListingsSeen
{
    private const int CHUNK = 500;

    public function __construct(private readonly CatalogCacheVersion $versions) {}

    /**
     * @return int number of listings confirmed
     */
    public function handle(int $feedSourceId, int $feedRunId, DateTimeImmutable $observedAt): int
    {
        $observedAt = $observedAt->setTimezone(new DateTimeZone('UTC'));
        $refreshOffers = (bool) config('comparo.feeds.unchanged_refreshes_freshness', true);
        $confirmed = 0;
        $productIds = [];

        MerchantProduct::query()
            ->where('feed_source_id', $feedSourceId)
            ->where('status', ListingStatus::Active->value)
            ->select('id')
            ->chunkById(self::CHUNK, function (Collection $listings) use ($feedRunId, $observedAt, $refreshOffers, &$confirmed, &$productIds): void {
                /** @var list<int> $listingIds */
                $listingIds = $listings->pluck('id')->map(static fn (mixed $value): int => (int) $value)->all();

                DB::transaction(function () use ($listingIds, $feedRunId, $observedAt, $refreshOffers, &$productIds): void {
                    MerchantProduct::query()->whereKey($listingIds)->toBase()->update([
                        'last_seen_at' => $observedAt,
                        'last_seen_run_id' => $feedRunId,
                    ]);

                    if ($refreshOffers) {
                        $productIds = [...$productIds, ...$this->refreshOffers($listingIds, $feedRunId, $observedAt)];
                    }
                });

                $confirmed += count($listingIds);
            });

        $this->bumpAfterCommit(array_values(array_unique($productIds)));

        return $confirmed;
    }

    /**
     * @param  list<int>  $listingIds
     * @return list<int> products whose cached comparison changed
     */
    private function refreshOffers(array $listingIds, int $feedRunId, DateTimeImmutable $observedAt): array
    {
        $offers = Offer::query()
            ->whereIn('merchant_product_id', $listingIds)
            ->where('is_active', true);

        $productIds = (clone $offers)->where('source_updated_at', '<', $observedAt)->distinct()->pluck('product_id')->map(static fn (mixed $value): int => (int) $value)->all();

        (clone $offers)->toBase()->update(['last_feed_run_id' => $feedRunId]);
        (clone $offers)->where('source_updated_at', '<', $observedAt)->toBase()->update(['source_updated_at' => $observedAt]);

        return array_values($productIds);
    }

    /**
     * @param  list<int>  $productIds
     */
    private function bumpAfterCommit(array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        DB::afterCommit(function () use ($productIds): void {
            foreach ($productIds as $productId) {
                $this->versions->bumpProduct($productId);
            }
        });
    }
}
