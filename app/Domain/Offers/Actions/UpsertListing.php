<?php

namespace App\Domain\Offers\Actions;

use App\Domain\Offers\Exceptions\SkuOwnedByOtherSource;
use App\Domain\Offers\ListingStatus;
use App\Models\MerchantProduct;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * The sole writer of merchant listing identity (merchant_products), keyed by
 * (merchant_id, merchant_sku).
 *
 * Every observation refreshes the listing's facts and marks it seen (active,
 * missing_run_count 0). It never touches the product link or match state —
 * that is {@see LinkListing}'s job, driven by the Matching context.
 *
 * Ownership: a SKU owned by a feed source can only be written by that source.
 * A listing without a source (manual or imported) is adopted by the first
 * feed source that observes it.
 */
final class UpsertListing
{
    /**
     * @throws SkuOwnedByOtherSource
     */
    public function handle(ListingObservation $observation): UpsertedListing
    {
        return DB::transaction(function () use ($observation): UpsertedListing {
            $listing = $this->lockedListing($observation);

            if ($listing === null) {
                $listing = MerchantProduct::query()->createOrFirst(
                    ['merchant_id' => $observation->merchantId, 'merchant_sku' => $observation->merchantSku],
                    [...$this->attributes($observation), 'first_seen_at' => $this->utc($observation)],
                );

                if ($listing->wasRecentlyCreated) {
                    return new UpsertedListing($listing, created: true, factsChanged: true, previousStatus: null);
                }

                // Lost a creation race: continue as an update of the winner's row.
                $listing = $this->lockedListing($observation) ?? $listing;
            }

            $this->guardOwnership($listing, $observation);

            $previousStatus = $listing->status;
            $factsChanged = $listing->facts_fingerprint !== $observation->factsFingerprint;

            $listing->fill($this->attributes($observation));
            if ($listing->first_seen_at === null) {
                $listing->fill(['first_seen_at' => $this->utc($observation)]);
            }
            $listing->save();

            return new UpsertedListing($listing, created: false, factsChanged: $factsChanged, previousStatus: $previousStatus);
        });
    }

    private function utc(ListingObservation $observation): DateTimeImmutable
    {
        return $observation->observedAt->setTimezone(new DateTimeZone('UTC'));
    }

    private function lockedListing(ListingObservation $observation): ?MerchantProduct
    {
        return MerchantProduct::query()
            ->where('merchant_id', $observation->merchantId)
            ->where('merchant_sku', $observation->merchantSku)
            ->lockForUpdate()
            ->first();
    }

    private function guardOwnership(MerchantProduct $listing, ListingObservation $observation): void
    {
        if ($listing->feed_source_id !== null && $listing->feed_source_id !== $observation->feedSourceId) {
            throw new SkuOwnedByOtherSource(
                $observation->merchantId,
                $observation->merchantSku,
                $listing->feed_source_id,
                $observation->feedSourceId,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(ListingObservation $observation): array
    {
        return [
            'feed_source_id' => $observation->feedSourceId,
            'external_id' => $observation->externalId,
            'title' => $observation->title,
            'ean' => $observation->ean,
            'brand_raw' => $observation->brandRaw,
            'pack_raw' => $observation->packRaw,
            'variant_raw' => $observation->variantRaw,
            'category_raw' => $observation->categoryRaw,
            'image_url' => $observation->imageUrl,
            'url' => $observation->url,
            'raw_payload' => $observation->rawPayload,
            'content_hash' => $observation->contentHash,
            'facts_fingerprint' => $observation->factsFingerprint,
            'status' => ListingStatus::Active,
            'missing_run_count' => 0,
            'last_seen_at' => $this->utc($observation),
            'last_seen_run_id' => $observation->feedRunId,
        ];
    }
}
