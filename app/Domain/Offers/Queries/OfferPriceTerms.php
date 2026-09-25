<?php

namespace App\Domain\Offers\Queries;

use App\Domain\Pricing\Currency\ComparisonRates;
use App\Domain\Pricing\Currency\ExchangeRates;
use App\Domain\Pricing\LandedPrice\CouponTerms;
use App\Domain\Pricing\LandedPrice\MerchantTerms;
use App\Domain\Pricing\LandedPrice\MerchantTermsConverter;
use App\Domain\Pricing\LandedPrice\ShippingTerms;
use App\Domain\Shared\Money;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\Merchant;
use App\Models\MerchantShippingZone;
use App\Models\Offer;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Loads each offer's merchant terms for one market (zone rate, free-shipping
 * threshold in the merchant currency, coupons) and brings them into the
 * offer's currency with the dated rates valid at `$now` (either direction,
 * ExchangeRates::conversionEitherWay), so the pure landed-price calculator
 * only ever sees one currency. Rates are loaded once per currency pair and
 * only when some amount is in another currency than its offer.
 *
 * Expects the offers' `merchant.shippingZones` (filtered to the market) and
 * `merchant.coupons.countries` relations to be loaded.
 */
final class OfferPriceTerms
{
    public function __construct(
        private readonly ExchangeRates $exchangeRates,
        private readonly MerchantTermsConverter $converter,
    ) {}

    /**
     * Terms in the offer currency, keyed by offer id. An offer whose merchant
     * has no zone in the market is absent; an offer whose shipping cost cannot
     * be expressed in its currency (no known rate) maps to null.
     *
     * @param  Collection<int, Offer>  $offers
     * @return array<int, MerchantTerms|null>
     */
    public function forMarket(Collection $offers, DateTimeImmutable $now): array
    {
        $raw = [];
        $sources = [];

        foreach ($offers as $offer) {
            $zone = $offer->merchant->shippingZones->first();

            if ($zone === null) {
                continue;
            }

            $raw[$offer->id] = $this->rawTerms($offer->merchant, $zone);

            foreach ($raw[$offer->id]->currencies() as $currency) {
                $sources[$offer->currency][$currency] = $currency;
            }
        }

        $rates = [];
        foreach ($sources as $target => $currencies) {
            $rates[$target] = $this->exchangeRates->comparisonRates(array_values($currencies), $target, $now);
        }

        $terms = [];
        foreach ($offers as $offer) {
            if (isset($raw[$offer->id])) {
                $terms[$offer->id] = $this->converter->inCurrency($raw[$offer->id], $rates[$offer->currency] ?? new ComparisonRates($offer->currency, []));
            }
        }

        return $terms;
    }

    private function rawTerms(Merchant $merchant, MerchantShippingZone $zone): MerchantTerms
    {
        return new MerchantTerms(
            shipping: new ShippingTerms($zone->cost_minor, $zone->currency, $zone->min_days, $zone->max_days, $zone->carrier),
            // The threshold is a merchant setting, in the merchant's currency.
            freeShippingThreshold: $merchant->free_shipping_threshold_minor === null
                ? null
                : Money::of($merchant->free_shipping_threshold_minor, $merchant->currency),
            coupons: array_values($merchant->coupons->map(fn (Coupon $coupon): CouponTerms => $this->couponTerms($coupon))->all()),
        );
    }

    private function couponTerms(Coupon $coupon): CouponTerms
    {
        return new CouponTerms(
            id: $coupon->id,
            code: $coupon->code,
            title: $coupon->title,
            type: $coupon->type,
            percentOffBasisPoints: $coupon->percent_off === null ? null : (int) round((float) $coupon->percent_off * 100),
            amountOffMinor: $coupon->amount_off_minor,
            minOrderMinor: $coupon->min_order_minor,
            currency: $coupon->currency,
            marketCodes: array_values($coupon->countries->map(static fn (Country $country): string => $country->code)->all()),
            startsAt: $coupon->starts_at?->toImmutable(),
            endsAt: $coupon->ends_at->toImmutable(),
            state: $coupon->verification_state,
            exclusive: $coupon->is_exclusive,
        );
    }
}
