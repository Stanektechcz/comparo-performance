<?php

namespace App\Domain\Offers\Actions;

use App\Domain\Offers\Events\OfferPublished;
use App\Domain\Offers\Exceptions\ListingNotLinked;
use App\Domain\Pricing\Actions\RecordPriceSnapshot;
use App\Domain\Pricing\Anomalies\PriceAnomalyDetector;
use App\Domain\Pricing\Anomalies\PriceAnomalyFinding;
use App\Domain\Pricing\Events\PriceChanged;
use App\Domain\Pricing\History\SnapshotPolicy;
use App\Domain\Pricing\History\SnapshotReason;
use App\Domain\Shared\Money;
use App\Models\MerchantProduct;
use App\Models\Offer;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Publishes the current terms of a linked merchant listing as its offer
 * (docs/architecture/phase-2-feeds-matching.md §6).
 *
 * In one transaction: lock the listing (serialises offer creation) and the
 * offer, create the offer or compare terms and write only when something
 * changed, reactivate a deactivated offer, flag price anomalies, and record
 * the price snapshot the {@see SnapshotPolicy}
 * asks for. Events are dispatched after commit.
 *
 * Identical terms are `unchanged`: no offer write, no events, no cache bump.
 * Only the freshness columns (source_updated_at moving forward,
 * last_feed_run_id, source) are refreshed, without touching updated_at —
 * the merchant re-confirmed the same price.
 */
final class PublishOffer
{
    public function __construct(
        private readonly PriceAnomalyDetector $anomalies,
        private readonly RecordPriceSnapshot $snapshots,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @throws ListingNotLinked
     */
    public function handle(MerchantProduct $listing, OfferTerms $terms, PublishContext $context): PublishResult
    {
        return DB::transaction(function () use ($listing, $terms, $context): PublishResult {
            $listing = MerchantProduct::query()->lockForUpdate()->findOrFail($listing->id);

            if ($listing->product_id === null || ! $listing->match_status->isLinked()) {
                throw ListingNotLinked::forListing($listing->id, $listing->match_status->value);
            }

            $offer = Offer::query()->where('merchant_product_id', $listing->id)->lockForUpdate()->first();
            $finding = $this->detectAnomaly($listing->product_id, $offer?->id, $terms);
            $observedAt = $context->observedAt->setTimezone(new DateTimeZone('UTC'));
            $sourceUpdatedAt = ($terms->sourceUpdatedAt ?? $context->observedAt)->setTimezone(new DateTimeZone('UTC'));

            $previousPrice = $offer === null ? null : Money::of($offer->price_minor, $offer->currency);

            if ($offer === null) {
                $offer = $this->create($listing, $terms, $context, $finding, $sourceUpdatedAt);
                $outcome = PublishOutcome::Created;
            } else {
                $outcome = $this->update($offer, $listing, $terms, $context, $finding, $sourceUpdatedAt, $observedAt);
            }

            $newPrice = Money::of($offer->price_minor, $offer->currency);
            $priceChanged = $previousPrice !== null && ! $previousPrice->equals($newPrice);
            $snapshotReason = $this->snapshots->recordIfDue($offer, $context->source, $context->feedRunId, $observedAt);

            $this->dispatchEvents($offer, $outcome, $context, $priceChanged ? $previousPrice : null, $snapshotReason);

            return new PublishResult($offer->id, $outcome, $priceChanged, $snapshotReason, $offer->anomaly);
        });
    }

    private function detectAnomaly(int $productId, ?int $offerId, OfferTerms $terms): ?PriceAnomalyFinding
    {
        $otherPrices = Offer::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where('currency', $terms->price->currency)
            ->when($offerId !== null, static fn ($query) => $query->whereKeyNot($offerId))
            ->pluck('price_minor')
            ->all();

        return $this->anomalies->detect($terms->price->minor, array_map(intval(...), array_values($otherPrices)));
    }

    private function create(MerchantProduct $listing, OfferTerms $terms, PublishContext $context, ?PriceAnomalyFinding $finding, DateTimeImmutable $sourceUpdatedAt): Offer
    {
        return Offer::query()->create([
            ...$this->termAttributes($listing, $terms, $finding),
            'merchant_product_id' => $listing->id,
            'is_active' => true,
            ...$this->freshnessAttributes($context, $sourceUpdatedAt),
        ]);
    }

    private function update(
        Offer $offer,
        MerchantProduct $listing,
        OfferTerms $terms,
        PublishContext $context,
        ?PriceAnomalyFinding $finding,
        DateTimeImmutable $sourceUpdatedAt,
        DateTimeImmutable $observedAt,
    ): PublishOutcome {
        $previousReference = $offer->reference_price_minor;
        $reactivating = ! $offer->is_active;

        $offer->fill($this->termAttributes($listing, $terms, $finding));

        if (! $reactivating && ! $offer->isDirty()) {
            $this->refreshFreshness($offer, $context, $sourceUpdatedAt);

            return PublishOutcome::Unchanged;
        }

        if ($terms->referencePrice === null) {
            $offer->fill(['reference_price_raised_at' => null]);
        } elseif ($previousReference === null || $terms->referencePrice->minor > $previousReference) {
            // Input of fake-discount detection: when the "was" price last went up.
            $offer->fill(['reference_price_raised_at' => $observedAt]);
        }

        if ($reactivating) {
            $offer->fill(['is_active' => true, 'deactivated_at' => null, 'deactivation_reason' => null]);
        }

        $offer->fill($this->freshnessAttributes($context, $sourceUpdatedAt))->save();

        return $reactivating ? PublishOutcome::Reactivated : PublishOutcome::Updated;
    }

    /**
     * An unchanged offer is not saved through Eloquent (no model events, no
     * updated_at, no cache bump); only its freshness moves forward.
     */
    private function refreshFreshness(Offer $offer, PublishContext $context, DateTimeImmutable $sourceUpdatedAt): void
    {
        $fresher = $offer->source_updated_at->getTimestamp() < $sourceUpdatedAt->getTimestamp();

        if (! $fresher && $offer->last_feed_run_id === $context->feedRunId && $offer->source === $context->source->value) {
            return;
        }

        $attributes = [
            'last_feed_run_id' => $context->feedRunId,
            'source' => $context->source->value,
            ...($fresher ? ['source_updated_at' => $sourceUpdatedAt] : []),
        ];

        Offer::query()->whereKey($offer->id)->toBase()->update(
            array_map(static fn (mixed $value): mixed => $value instanceof DateTimeImmutable ? $offer->fromDateTime($value) : $value, $attributes),
        );
        $offer->forceFill($attributes)->syncOriginal();
    }

    /**
     * @return array<string, mixed>
     */
    private function termAttributes(MerchantProduct $listing, OfferTerms $terms, ?PriceAnomalyFinding $finding): array
    {
        return [
            'product_id' => $listing->product_id,
            'merchant_id' => $listing->merchant_id,
            'variant_label' => $terms->variantLabel,
            'pack_label' => $terms->packLabel,
            'price_minor' => $terms->price->minor,
            'currency' => $terms->price->currency,
            'reference_price_minor' => $terms->referencePrice?->minor,
            'availability' => $terms->availability,
            'stock_quantity' => $terms->stockQuantity,
            'url' => $terms->url,
            'anomaly' => $finding?->anomaly,
            'anomaly_reference_minor' => $finding?->medianMinor,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function freshnessAttributes(PublishContext $context, DateTimeImmutable $sourceUpdatedAt): array
    {
        return [
            'source' => $context->source->value,
            'last_feed_run_id' => $context->feedRunId,
            'source_updated_at' => $sourceUpdatedAt,
        ];
    }

    private function dispatchEvents(Offer $offer, PublishOutcome $outcome, PublishContext $context, ?Money $previousPrice, ?SnapshotReason $snapshotReason): void
    {
        if ($outcome === PublishOutcome::Unchanged) {
            return;
        }

        $this->events->dispatch(new OfferPublished(
            offerId: $offer->id,
            merchantProductId: $offer->merchant_product_id,
            productId: $offer->product_id,
            merchantId: $offer->merchant_id,
            outcome: $outcome->value,
            feedRunId: $context->feedRunId,
        ));

        if ($previousPrice === null) {
            return;
        }

        $this->events->dispatch(new PriceChanged(
            offerId: $offer->id,
            productId: $offer->product_id,
            merchantId: $offer->merchant_id,
            oldPriceMinor: $previousPrice->minor,
            newPriceMinor: $offer->price_minor,
            previousCurrency: $previousPrice->currency,
            currency: $offer->currency,
            reason: ($snapshotReason ?? SnapshotReason::PriceChange)->value,
            feedRunId: $context->feedRunId,
        ));
    }
}
