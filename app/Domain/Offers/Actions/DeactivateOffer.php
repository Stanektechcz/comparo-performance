<?php

namespace App\Domain\Offers\Actions;

use App\Domain\Offers\Events\OfferDeactivated;
use App\Domain\Offers\OfferDeactivationReason;
use App\Models\Offer;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Hides an offer from every comparison (is_active = false). Offers are never
 * deleted: price history and reactivation depend on the row. Idempotent —
 * an already inactive offer is left untouched and no event is dispatched.
 */
final class DeactivateOffer
{
    public function __construct(private readonly Dispatcher $events) {}

    /**
     * @return bool true when this call deactivated the offer
     */
    public function handle(Offer $offer, OfferDeactivationReason $reason, DateTimeImmutable $observedAt): bool
    {
        return DB::transaction(function () use ($offer, $reason, $observedAt): bool {
            $locked = Offer::query()->lockForUpdate()->findOrFail($offer->id);

            if (! $locked->is_active) {
                return false;
            }

            $locked->fill([
                'is_active' => false,
                'deactivated_at' => $observedAt->setTimezone(new DateTimeZone('UTC')),
                'deactivation_reason' => $reason,
            ])->save();

            $this->events->dispatch(new OfferDeactivated(
                offerId: $locked->id,
                productId: $locked->product_id,
                merchantId: $locked->merchant_id,
                reason: $reason->value,
            ));

            return true;
        });
    }
}
