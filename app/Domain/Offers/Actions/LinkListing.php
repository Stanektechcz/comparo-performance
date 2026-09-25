<?php

namespace App\Domain\Offers\Actions;

use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Offers\Events\OfferRelinked;
use App\Domain\Offers\OfferDeactivationReason;
use App\Models\MerchantProduct;
use App\Models\Offer;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Writes a listing's link to a canonical product. It does not decide matches:
 * the Matching context decides and calls this action.
 *
 * In one transaction it updates the listing's link and match state and keeps
 * the listing's offer on the same product:
 * - relinked to another product → offers.product_id follows, `OfferRelinked` (old + new product);
 * - unlinked (no product)       → the offer is deactivated with reason `manual`
 *   (offers.product_id is NOT NULL, so the offer keeps its last product while hidden).
 *
 * `ListingMatchStatus` is the column type of merchant_products.match_status;
 * it is the only Matching symbol the Offers context may use (tests/Architecture/WritePathTest.php).
 */
final class LinkListing
{
    public function __construct(
        private readonly DeactivateOffer $deactivateOffer,
        private readonly Dispatcher $events,
    ) {}

    public function handle(
        MerchantProduct $listing,
        ?int $productId,
        ListingMatchStatus $status,
        ?int $score,
        DateTimeImmutable $decidedAt,
        ?int $matchingDecisionId = null,
    ): MerchantProduct {
        $this->guardConsistency($productId, $status);

        return DB::transaction(function () use ($listing, $productId, $status, $score, $decidedAt, $matchingDecisionId): MerchantProduct {
            $locked = MerchantProduct::query()->lockForUpdate()->findOrFail($listing->id);

            $locked->fill([
                'product_id' => $productId,
                'match_status' => $status,
                'match_score' => $score,
                'matched_at' => $productId === null ? null : $decidedAt->setTimezone(new DateTimeZone('UTC')),
            ]);
            if ($matchingDecisionId !== null) {
                $locked->fill(['current_matching_decision_id' => $matchingDecisionId]);
            }
            $locked->save();

            $offer = Offer::query()->where('merchant_product_id', $locked->id)->lockForUpdate()->first();

            if ($offer !== null) {
                $this->moveOffer($offer, $productId, $decidedAt);
            }

            return $locked;
        });
    }

    private function moveOffer(Offer $offer, ?int $productId, DateTimeImmutable $decidedAt): void
    {
        if ($productId === null) {
            $this->deactivateOffer->handle($offer, OfferDeactivationReason::Manual, $decidedAt);

            return;
        }

        $previousProductId = $offer->product_id;

        if ($previousProductId === $productId) {
            return;
        }

        $offer->fill(['product_id' => $productId])->save();

        $this->events->dispatch(new OfferRelinked(
            offerId: $offer->id,
            merchantProductId: $offer->merchant_product_id,
            merchantId: $offer->merchant_id,
            previousProductId: $previousProductId,
            productId: $productId,
        ));
    }

    private function guardConsistency(?int $productId, ListingMatchStatus $status): void
    {
        if ($productId === null && $status->isLinked()) {
            throw new InvalidArgumentException("Match status [{$status->value}] requires a product.");
        }

        if ($productId !== null && in_array($status, [ListingMatchStatus::Unmatched, ListingMatchStatus::Rejected], true)) {
            throw new InvalidArgumentException("Match status [{$status->value}] cannot carry a product.");
        }
    }
}
