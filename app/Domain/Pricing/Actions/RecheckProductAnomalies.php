<?php

namespace App\Domain\Pricing\Actions;

use App\Domain\Platform\Cache\CatalogCacheVersion;
use App\Domain\Pricing\Anomalies\PriceAnomalyDetector;
use App\Models\Offer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Re-judges the anomaly flag of every active offer of the given products
 * against the product's other active offers in the same currency
 * ({@see PriceAnomalyDetector}). A publish only judges the offer being
 * written; a later price move elsewhere can make a flag stale or make another
 * offer an outlier, so the feed pipeline rechecks the products it touched.
 *
 * Only changed flags are written (no Eloquent events); the cached comparison
 * of each product whose flags changed is invalidated once, after commit.
 */
final class RecheckProductAnomalies
{
    private const int CHUNK = 100;

    public function __construct(
        private readonly PriceAnomalyDetector $detector,
        private readonly CatalogCacheVersion $versions,
    ) {}

    /**
     * @param  list<int>  $productIds
     * @return int offers whose flag changed
     */
    public function handle(array $productIds): int
    {
        $changedOffers = 0;

        foreach (array_chunk(array_values(array_unique($productIds)), self::CHUNK) as $chunk) {
            $changedOffers += DB::transaction(fn (): int => $this->recheck($chunk));
        }

        return $changedOffers;
    }

    /**
     * @param  list<int>  $productIds
     */
    private function recheck(array $productIds): int
    {
        $offers = Offer::query()
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'product_id', 'price_minor', 'currency', 'anomaly', 'anomaly_reference_minor']);

        $changedProducts = [];
        $changedOffers = 0;

        foreach ($offers->groupBy(static fn (Offer $offer): string => $offer->product_id.'|'.$offer->currency) as $group) {
            foreach ($group as $offer) {
                if ($this->recheckOffer($offer, $group)) {
                    $changedProducts[$offer->product_id] = true;
                    $changedOffers++;
                }
            }
        }

        $this->bumpAfterCommit(array_keys($changedProducts));

        return $changedOffers;
    }

    /**
     * @param  Collection<int, Offer>  $group  active offers of the same product and currency
     */
    private function recheckOffer(Offer $offer, Collection $group): bool
    {
        $others = array_values($group
            ->reject(static fn (Offer $other): bool => $other->id === $offer->id)
            ->map(static fn (Offer $other): int => (int) $other->price_minor)
            ->all());

        $finding = $this->detector->detect((int) $offer->price_minor, $others);
        $currentReference = $offer->anomaly_reference_minor === null ? null : (int) $offer->anomaly_reference_minor;
        $anomaly = $finding?->anomaly;
        $reference = $finding?->medianMinor;

        if ($offer->anomaly === $anomaly && $currentReference === $reference) {
            return false;
        }

        Offer::query()->whereKey($offer->id)->toBase()->update([
            'anomaly' => $anomaly?->value,
            'anomaly_reference_minor' => $reference,
        ]);

        return true;
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
