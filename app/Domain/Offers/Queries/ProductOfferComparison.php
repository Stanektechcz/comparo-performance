<?php

namespace App\Domain\Offers\Queries;

use App\Domain\Catalog\Completeness\ProductCompleteness;
use App\Domain\Catalog\Completeness\ProductCompletenessService;
use App\Domain\Catalog\Completeness\ProductFacts;
use App\Domain\Compliance\ComplianceDecision;
use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Compliance\Queries\ComplianceResolver;
use App\Domain\Merchants\Queries\MerchantScores;
use App\Domain\Offers\Ranking\RankingContext;
use App\Domain\Offers\Ranking\RankingService;
use App\Domain\Offers\Ranking\RankingWeights;
use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Pricing\Confidence\PriceConfidenceInput;
use App\Domain\Pricing\Confidence\PriceConfidenceService;
use App\Domain\Pricing\Currency\CurrencyConversion;
use App\Domain\Pricing\Currency\ExchangeRates;
use App\Domain\Pricing\History\PriceHistoryAnalyzer;
use App\Domain\Pricing\LandedPrice\CouponTerms;
use App\Domain\Pricing\LandedPrice\LandedPriceCalculator;
use App\Domain\Pricing\LandedPrice\LandedPriceInput;
use App\Domain\Pricing\LandedPrice\ShippingTerms;
use App\Domain\Pricing\MarketStats\MarketListing;
use App\Domain\Pricing\MarketStats\MarketStats;
use App\Domain\Pricing\MarketStats\MarketStatsCalculator;
use App\Domain\Pricing\Queries\ProductPriceHistory;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductVariant;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the offer comparison of one product in one market:
 *
 *   market → compliance → shipping eligibility → landed price → market
 *   baseline → trust / risk / completeness → ComparoRank → publishability →
 *   organic order.
 *
 * This is the query layer: it loads data and builds immutable contexts. Every
 * number is computed by a pure service (docs/adr/0004, 0007).
 */
final class ProductOfferComparison
{
    private const int MAX_VALIDITY_SECONDS = 600;

    public function __construct(
        private readonly ComplianceResolver $complianceResolver,
        private readonly ActiveRankingWeights $rankingWeights,
        private readonly ProductPriceHistory $priceHistory,
        private readonly ExchangeRates $exchangeRates,
        private readonly LandedPriceCalculator $landedPrice,
        private readonly MarketStatsCalculator $marketStats,
        private readonly RankingService $ranking,
        private readonly MerchantScores $merchantScores,
        private readonly ProductCompletenessService $completeness,
        private readonly PriceHistoryAnalyzer $historyAnalyzer,
        private readonly PriceConfidenceService $priceConfidence,
    ) {}

    public function compare(Product $product, MarketContext $market, DateTimeImmutable $now, ?ComplianceDecision $compliance = null): OfferComparison
    {
        $compliance ??= $this->complianceResolver->decide($product->id, $market);
        $weights = $this->rankingWeights->current();
        $conversion = $this->exchangeRates->conversion((string) config('comparo.comparison_currency'), $market->currency, $now);

        if (! $compliance->status->offersVisible()) {
            return $this->withoutOffers($market, $compliance, $weights, $conversion, $now);
        }

        $offers = $this->loadOffers($product, $market, $now);
        $stats = $this->marketStats->calculate(array_values($offers->map(static fn (Offer $offer): MarketListing => new MarketListing(
            $offer->price_minor,
            $offer->merchant->shippingZones->first()?->cost_minor,
            $offer->merchant->free_shipping_threshold_minor,
        ))->all()));

        $completeness = $this->completenessOf($product);
        $historyMedian = $this->historyMedian($product->id, $now);

        $ranked = [];
        foreach ($offers as $offer) {
            if ($offer->merchant->shippingZones->isEmpty()) {
                continue;
            }
            $ranked[] = $this->evaluate($offer, $market, $compliance, $stats, $completeness, $historyMedian, $weights, $conversion, $now);
        }

        $public = array_values(array_filter($ranked, static fn (ComparedOffer $offer): bool => $offer->isPublishable()));
        usort($public, static fn (ComparedOffer $a, ComparedOffer $b): int => [$b->rank->score, $a->price->total->minor, $a->offerId]
            <=> [$a->rank->score, $b->price->total->minor, $b->offerId]);

        return new OfferComparison(
            market: $market,
            compliance: $compliance,
            offers: $public,
            ranked: $ranked,
            totalOffers: $offers->count(),
            notShipping: $offers->count() - count($ranked),
            withheldFlagged: count($ranked) - count($public),
            bestValueOfferId: $compliance->status->isRecommendable() ? $this->bestValue($public) : null,
            lowestTotal: $public === [] ? null : array_reduce(
                $public,
                static fn ($lowest, ComparedOffer $offer) => $lowest === null || $offer->price->total->isLessThan($lowest) ? $offer->price->total : $lowest,
            ),
            marketStats: $stats,
            weights: $weights,
            conversion: $conversion,
            evaluatedAt: $now,
            validUntil: $this->validUntil($offers, $now),
        );
    }

    /**
     * @return Collection<int, Offer>
     */
    private function loadOffers(Product $product, MarketContext $market, DateTimeImmutable $now): Collection
    {
        return Offer::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->whereHas('merchant', static fn ($query) => $query->listed())
            ->with([
                'merchant' => static fn ($query) => $query->withCount('shippingZones'),
                'merchant.latestTrustSignal',
                'merchant.riskEvents' => static fn ($query) => $query->whereNull('resolved_at')->orderByDesc('detected_at'),
                'merchant.shippingZones' => static fn ($query) => $query->where('country_id', $market->countryId ?? 0),
                'merchant.coupons' => static fn ($query) => $query->where('ends_at', '>', $now)->orderBy('id'),
                'merchant.coupons.countries' => static fn ($query) => $query->where('countries.id', $market->countryId ?? 0),
            ])
            // Seed/insertion order is the final tie-breaker, as in the prototype.
            ->orderBy('id')
            ->get();
    }

    private function evaluate(
        Offer $offer,
        MarketContext $market,
        ComplianceDecision $compliance,
        MarketStats $stats,
        ProductCompleteness $completeness,
        int $historyMedian,
        RankingWeights $weights,
        ?CurrencyConversion $conversion,
        DateTimeImmutable $now,
    ): ComparedOffer {
        $merchant = $offer->merchant;
        $zone = $merchant->shippingZones->first();

        $price = $this->landedPrice->calculate(new LandedPriceInput(
            priceMinor: $offer->price_minor,
            currency: $offer->currency,
            marketCode: $market->code,
            priceFlagged: $offer->isPriceFlagged(),
            shipping: $zone === null ? null : new ShippingTerms($zone->cost_minor, $zone->currency, $zone->min_days, $zone->max_days, $zone->carrier),
            freeShippingThresholdMinor: $merchant->free_shipping_threshold_minor,
            coupons: array_values($merchant->coupons->map(fn (Coupon $coupon): CouponTerms => $this->couponTerms($coupon))->all()),
        ), $now);

        $trust = $this->merchantScores->trust($merchant);
        $freshnessHours = ($now->getTimestamp() - $offer->source_updated_at->getTimestamp()) / 3600;
        $referenceUnverified = $this->historyAnalyzer->hasUnverifiedReferencePrice($offer->reference_price_minor, $historyMedian);

        $rank = $this->ranking->rank(new RankingContext(
            totalMinor: $price->total->minor,
            marketMinTotalMinor: $stats->minTotalMinor ?: $price->total->minor,
            shippingMinor: $price->shipping->minor,
            marketShippingMedianMinor: $stats->shippingMedianMinor,
            deliveryDaysMax: $price->deliveryMaxDays ?? 99,
            merchantRating: (float) ($merchant->weighted_rating ?? $merchant->rating_average ?? 0),
            merchantReviewCount: $merchant->rating_count,
            merchantTrustScore: $trust->score,
            freshnessHours: $freshnessHours,
            availability: $offer->availability,
            hasValidCoupon: $price->coupon !== null,
            completeness: $completeness->ratio(),
            priceAnomaly: $offer->isPriceFlagged(),
            unverifiedReferencePrice: $referenceUnverified,
            outboundLinkProblem: ! $offer->link_status->isHealthy(),
            complianceUnknown: $compliance->status === ComplianceStatus::Unknown,
            complianceBlocked: $compliance->status->isBlocked(),
            riskLevel: $this->merchantScores->risk($merchant)->level,
        ), $weights, $now);

        return new ComparedOffer(
            offerId: $offer->id,
            merchantId: $merchant->id,
            merchantName: $merchant->name,
            merchantSlug: $merchant->slug,
            merchantVerified: $merchant->isVerified(),
            variantLabel: $offer->variant_label,
            packLabel: $offer->pack_label,
            availability: $offer->availability,
            url: $offer->url,
            referencePriceMinor: $offer->reference_price_minor,
            referencePriceUnverified: $referenceUnverified,
            sourceUpdatedAt: $offer->source_updated_at->toImmutable(),
            freshnessHours: $freshnessHours,
            priceFlagged: $offer->isPriceFlagged(),
            price: $price,
            displayTotal: $conversion?->convert($price->total),
            rank: $rank,
            trust: $trust,
            priceConfidence: $this->priceConfidence->evaluate(new PriceConfidenceInput(
                ageHours: $freshnessHours,
                merchantVerified: $merchant->isVerified(),
                priceAnomaly: $offer->anomaly !== null,
                priceMinor: $offer->price_minor,
                hasAvailability: true,
                hasShippingZones: (int) $merchant->getAttribute('shipping_zones_count') > 0,
                linkHealthy: $offer->link_status->isHealthy(),
            )),
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

    private function completenessOf(Product $product): ProductCompleteness
    {
        $product->loadCount([
            'ingredients',
            'variants as flavour_variants_count' => static fn ($query) => $query->where('kind', ProductVariant::FLAVOUR),
        ]);

        return $this->completeness->evaluate(new ProductFacts(
            hasEan: filled($product->ean),
            // brand_id and category_id are non-nullable foreign keys (every
            // product has a brand and a category).
            hasBrand: true,
            hasPackSize: filled($product->pack_label),
            hasCategory: true,
            ingredientCount: (int) $product->getAttribute('ingredients_count'),
            hasServings: (bool) $product->servings,
            descriptionLength: mb_strlen((string) $product->description),
            flavourVariantCount: (int) $product->getAttribute('flavour_variants_count'),
        ));
    }

    private function historyMedian(int $productId, DateTimeImmutable $now): int
    {
        $lows = $this->priceHistory->dailyLows($productId, $now)['lows'];

        return $lows === [] ? 0 : $this->historyAnalyzer->stats($lows)->median;
    }

    /**
     * Prototype `bestValue`: among best-buy-eligible offers (else all public
     * ones) the highest rank wins; ties keep the listing order.
     *
     * @param  list<ComparedOffer>  $public  already in listing order
     */
    private function bestValue(array $public): ?int
    {
        $eligible = array_values(array_filter($public, static fn (ComparedOffer $offer): bool => $offer->rank->eligibleBestBuy));

        return ($eligible[0] ?? $public[0] ?? null)?->offerId;
    }

    /**
     * Cached results must not outlive the next coupon start or expiry.
     *
     * @param  Collection<int, Offer>  $offers
     */
    private function validUntil(Collection $offers, DateTimeImmutable $now): DateTimeImmutable
    {
        $limit = $now->add(new DateInterval('PT'.self::MAX_VALIDITY_SECONDS.'S'));

        foreach ($offers->pluck('merchant')->unique('id') as $merchant) {
            foreach ($merchant->coupons as $coupon) {
                foreach ([$coupon->starts_at, $coupon->ends_at] as $boundary) {
                    if ($boundary !== null && $boundary->toImmutable() > $now && $boundary->toImmutable() < $limit) {
                        $limit = $boundary->toImmutable();
                    }
                }
            }
        }

        return $limit;
    }

    private function withoutOffers(
        MarketContext $market,
        ComplianceDecision $compliance,
        RankingWeights $weights,
        ?CurrencyConversion $conversion,
        DateTimeImmutable $now,
    ): OfferComparison {
        return new OfferComparison(
            market: $market,
            compliance: $compliance,
            offers: [],
            ranked: [],
            totalOffers: 0,
            notShipping: 0,
            withheldFlagged: 0,
            bestValueOfferId: null,
            lowestTotal: null,
            marketStats: new MarketStats(0, 0, 0, 0),
            weights: $weights,
            conversion: $conversion,
            evaluatedAt: $now,
            validUntil: $now->add(new DateInterval('PT'.self::MAX_VALIDITY_SECONDS.'S')),
        );
    }
}
