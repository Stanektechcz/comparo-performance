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
use App\Domain\Pricing\Currency\ComparisonRates;
use App\Domain\Pricing\Currency\CurrencyConversion;
use App\Domain\Pricing\Currency\ExchangeRates;
use App\Domain\Pricing\History\PriceHistoryAnalyzer;
use App\Domain\Pricing\LandedPrice\LandedPrice;
use App\Domain\Pricing\LandedPrice\LandedPriceCalculator;
use App\Domain\Pricing\LandedPrice\MerchantTerms;
use App\Domain\Pricing\MarketStats\MarketListing;
use App\Domain\Pricing\MarketStats\MarketStats;
use App\Domain\Pricing\MarketStats\MarketStatsCalculator;
use App\Domain\Pricing\Queries\ProductPriceHistory;
use App\Domain\Shared\Money;
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
 *
 * Currencies: when the offers shipping to the market are priced in one
 * currency (every prototype market), baselines, ranking inputs and the
 * listing tie-break use their own minor units, exactly as ported. When they
 * span several currencies, this layer loads the dated rates valid at `$now`
 * into a ComparisonRates table: the market baseline and the ranking inputs
 * become comparison-currency minor units (rounded half away from zero), and
 * totals are compared unrounded (bcmath). An offer whose currency has no
 * known rate is left out of the baseline, ranked without a price or shipping
 * advantage and listed after comparable offers of equal rank. Displayed
 * amounts always stay in each offer's own currency.
 *
 * A merchant's zone rate, free-shipping threshold and coupon amounts are
 * first brought into each offer's currency (OfferPriceTerms, dated rates,
 * half away from zero). An offer whose shipping cost cannot be expressed in
 * its currency (no known rate) is left out of the comparison, the baseline
 * and the ranking, like an offer that does not ship to the market, and is
 * counted as `shippingUnavailable`. The pure calculator's currency checks are
 * therefore unreachable from here: a public page never fails on them.
 */
final class ProductOfferComparison
{
    private const int MAX_VALIDITY_SECONDS = 600;

    /** Decimal places compared when choosing the lowest total across currencies. */
    private const int COMPARISON_SCALE = 6;

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
        private readonly OfferPriceTerms $priceTerms,
        private readonly ProductHistoryMedians $historyMedians,
    ) {}

    public function compare(Product $product, MarketContext $market, DateTimeImmutable $now, ?ComplianceDecision $compliance = null): OfferComparison
    {
        $compliance ??= $this->complianceResolver->decide($product->id, $market);
        $weights = $this->rankingWeights->current();
        $conversion = $this->exchangeRates->conversion((string) config('comparo.comparison_currency'), $market->currency, $now);

        if (! $compliance->status->offersVisible()) {
            return $this->withoutOffers($market, $compliance, $weights, $conversion, $now);
        }

        $offers = $this->loadOffers([$product->id], $market, $now);

        return $this->assemble($offers, $market, $now, $compliance, $weights, $conversion, $this->completenessOf($product), $this->historyMedian($product->id, $now));
    }

    /**
     * The comparisons of a page of products (BACKLOG F-15), keyed by product
     * id: exactly what {@see self::compare()} returns for each product, but
     * the offers with their whole eager-load graph, the compliance decisions
     * missing from `$decisions`, the completeness counts and the history
     * medians are each loaded once for all products instead of per product
     * (a constant number of queries per page instead of ~8–10 per product).
     *
     * `$decisions`, when given, has the precondition of `compare()`'s
     * `$compliance` (what `ComplianceResolver::decide()` returns per product).
     *
     * @param  list<Product>  $products
     * @param  array<int, ComplianceDecision>|null  $decisions  keyed by product id
     * @return array<int, OfferComparison>
     */
    public function compareMany(array $products, MarketContext $market, DateTimeImmutable $now, ?array $decisions = null): array
    {
        $byId = [];
        foreach ($products as $product) {
            $byId[$product->id] = $product;
        }

        if ($byId === []) {
            return [];
        }

        $decisions = $this->decisionsFor(array_keys($byId), $market, $decisions ?? []);
        $weights = $this->rankingWeights->current();
        $conversion = $this->exchangeRates->conversion((string) config('comparo.comparison_currency'), $market->currency, $now);

        $visible = array_values(array_filter($byId, static fn (Product $product): bool => $decisions[$product->id]->status->offersVisible()));
        $visibleIds = array_map(static fn (Product $product): int => $product->id, $visible);
        $offersByProduct = $this->groupByProduct($this->loadOffers($visibleIds, $market, $now));
        $completeness = $this->completenessOfMany($visible);
        $medians = $this->historyMedians->forProducts($visibleIds, $now);

        $comparisons = [];
        foreach ($byId as $id => $product) {
            $comparisons[$id] = $decisions[$id]->status->offersVisible()
                ? $this->assemble($offersByProduct[$id] ?? new Collection, $market, $now, $decisions[$id], $weights, $conversion, $completeness[$id], $medians[$id])
                : $this->withoutOffers($market, $decisions[$id], $weights, $conversion, $now);
        }

        return $comparisons;
    }

    /**
     * @param  list<int>  $productIds
     * @param  array<int, ComplianceDecision>  $known
     * @return array<int, ComplianceDecision>
     */
    private function decisionsFor(array $productIds, MarketContext $market, array $known): array
    {
        $missing = array_values(array_filter($productIds, static fn (int $id): bool => ! isset($known[$id])));

        return $known + ($missing === [] ? [] : $this->complianceResolver->decideMany($missing, $market));
    }

    /**
     * @param  Collection<int, Offer>  $offers  in id order
     * @return array<int, Collection<int, Offer>> keyed by product id, each in id order
     */
    private function groupByProduct(Collection $offers): array
    {
        $grouped = [];
        foreach ($offers as $offer) {
            $grouped[$offer->product_id][] = $offer;
        }

        return array_map(static fn (array $group): Collection => new Collection($group), $grouped);
    }

    /**
     * @param  Collection<int, Offer>  $offers  the product's active offers of listed merchants, in id order
     */
    private function assemble(
        Collection $offers,
        MarketContext $market,
        DateTimeImmutable $now,
        ComplianceDecision $compliance,
        RankingWeights $weights,
        ?CurrencyConversion $conversion,
        ProductCompleteness $completeness,
        int $historyMedian,
    ): OfferComparison {
        $terms = $this->priceTerms->forMarket($offers, $now);
        $rates = $this->comparisonRates($offers, $terms, $now);
        $stats = $this->marketStats->calculate(array_values($offers->map(static fn (Offer $offer): MarketListing => new MarketListing(
            $offer->price_minor,
            ($terms[$offer->id] ?? null)?->shipping?->costMinor,
            ($terms[$offer->id] ?? null)?->freeShippingThreshold?->minor,
            $offer->currency,
        ))->all()), $rates);

        $ranked = [];
        foreach ($offers as $offer) {
            $offerTerms = $terms[$offer->id] ?? null;

            if ($offerTerms === null) {
                continue;
            }
            $ranked[] = $this->evaluate($offer, $offerTerms, $market, $compliance, $stats, $rates, $completeness, $historyMedian, $weights, $conversion, $now);
        }

        $public = array_values(array_filter($ranked, static fn (ComparedOffer $offer): bool => $offer->isPublishable()));
        $amounts = $this->comparableAmounts($public, $rates);
        $public = $this->inListingOrder($public, $amounts);

        return new OfferComparison(
            market: $market,
            compliance: $compliance,
            offers: $public,
            ranked: $ranked,
            totalOffers: $offers->count(),
            notShipping: $offers->count() - count($terms),
            withheldFlagged: count($ranked) - count($public),
            bestValueOfferId: $compliance->status->isRecommendable() ? $this->bestValue($public) : null,
            lowestTotal: $this->lowestTotal($public, $amounts),
            marketStats: $stats,
            weights: $weights,
            conversion: $conversion,
            evaluatedAt: $now,
            validUntil: $this->validUntil($offers, $now),
            shippingUnavailable: count($terms) - count($ranked),
        );
    }

    /**
     * @param  list<int>  $productIds
     * @return Collection<int, Offer>
     */
    private function loadOffers(array $productIds, MarketContext $market, DateTimeImmutable $now): Collection
    {
        if ($productIds === []) {
            return new Collection;
        }

        return Offer::query()
            ->whereIn('product_id', $productIds)
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
        MerchantTerms $terms,
        MarketContext $market,
        ComplianceDecision $compliance,
        MarketStats $stats,
        ?ComparisonRates $rates,
        ProductCompleteness $completeness,
        int $historyMedian,
        RankingWeights $weights,
        ?CurrencyConversion $conversion,
        DateTimeImmutable $now,
    ): ComparedOffer {
        $merchant = $offer->merchant;

        $price = $this->landedPrice->calculate(
            $terms->toInput($offer->price_minor, $offer->currency, $market->code, $offer->isPriceFlagged()),
            $now,
        );

        $trust = $this->merchantScores->trust($merchant);
        $freshnessHours = ($now->getTimestamp() - $offer->source_updated_at->getTimestamp()) / 3600;
        $referenceUnverified = $this->historyAnalyzer->hasUnverifiedReferencePrice($offer->reference_price_minor, $historyMedian);
        $amounts = $this->baselineAmounts($price, $stats, $rates);

        $rank = $this->ranking->rank(new RankingContext(
            totalMinor: $amounts['total'],
            marketMinTotalMinor: $stats->minTotalMinor ?: $amounts['total'],
            shippingMinor: $amounts['shipping'],
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
            amountsComparable: $amounts['comparable'],
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
            // The market-currency conversion only applies to totals in the comparison currency.
            displayTotal: $conversion !== null && $price->total->currency === $conversion->from ? $conversion->convert($price->total) : null,
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

    private function completenessOf(Product $product): ProductCompleteness
    {
        $product->loadCount(self::completenessCounts());

        return $this->completenessFromCounts($product);
    }

    /**
     * The completeness of many products, with their counts loaded in one query.
     *
     * @param  list<Product>  $products
     * @return array<int, ProductCompleteness> keyed by product id
     */
    private function completenessOfMany(array $products): array
    {
        if ($products === []) {
            return [];
        }

        (new Collection($products))->loadCount(self::completenessCounts());

        $completeness = [];
        foreach ($products as $product) {
            $completeness[$product->id] = $this->completenessFromCounts($product);
        }

        return $completeness;
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function completenessCounts(): array
    {
        return [
            'ingredients',
            'variants as flavour_variants_count' => static fn ($query) => $query->where('kind', ProductVariant::FLAVOUR),
        ];
    }

    private function completenessFromCounts(Product $product): ProductCompleteness
    {
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
     * The comparison rates of the offers priced for the market, or null when
     * they are all priced in one currency (then no rate is needed or loaded).
     *
     * @param  Collection<int, Offer>  $offers
     * @param  array<int, MerchantTerms|null>  $terms  keyed by offer id
     */
    private function comparisonRates(Collection $offers, array $terms, DateTimeImmutable $now): ?ComparisonRates
    {
        $currencies = [];
        foreach ($offers as $offer) {
            if (($terms[$offer->id] ?? null) !== null) {
                $currencies[$offer->currency] = $offer->currency;
            }
        }

        return count($currencies) > 1
            ? $this->exchangeRates->comparisonRates(array_values($currencies), (string) config('comparo.comparison_currency'), $now)
            : null;
    }

    /**
     * The offer's total and shipping in the currency of the market baseline:
     * its own minor units when it shares that currency (every single-currency
     * market), else converted by the comparison rates (half away from zero to
     * a whole minor unit). Without a known rate the amounts are raw and marked
     * not comparable, so the ranking gives them no price or shipping advantage.
     *
     * @return array{total: int, shipping: int, comparable: bool}
     */
    private function baselineAmounts(LandedPrice $price, MarketStats $stats, ?ComparisonRates $rates): array
    {
        if ($stats->currency === null || $stats->currency === $price->total->currency) {
            return ['total' => $price->total->minor, 'shipping' => $price->shipping->minor, 'comparable' => true];
        }

        $convertible = $rates !== null && $rates->target === $stats->currency;
        $total = $convertible ? $rates->convert($price->total) : null;
        $shipping = $convertible ? $rates->convert($price->shipping) : null;

        if ($total === null || $shipping === null) {
            return ['total' => $price->total->minor, 'shipping' => $price->shipping->minor, 'comparable' => false];
        }

        return ['total' => $total->minor, 'shipping' => $shipping->minor, 'comparable' => true];
    }

    /**
     * Organic order: higher rank, then lower total, then offer id. A
     * single-currency listing compares total minor units, as ported; a mixed
     * one compares exact comparison-currency amounts, and a total without a
     * known rate sorts after the comparable totals of equal rank.
     *
     * @param  list<ComparedOffer>  $public
     * @param  array<int, numeric-string|null>  $amounts  keyed by offer id
     * @return list<ComparedOffer>
     */
    private function inListingOrder(array $public, array $amounts): array
    {
        if (count(self::currenciesOf($public)) <= 1) {
            usort($public, static fn (ComparedOffer $a, ComparedOffer $b): int => [$b->rank->score, $a->price->total->minor, $a->offerId]
                <=> [$a->rank->score, $b->price->total->minor, $b->offerId]);

            return $public;
        }

        usort($public, static fn (ComparedOffer $a, ComparedOffer $b): int => ($b->rank->score <=> $a->rank->score)
            ?: self::compareAmounts($amounts[$a->offerId], $amounts[$b->offerId])
            ?: ($a->offerId <=> $b->offerId));

        return $public;
    }

    /**
     * The lowest landed total of the public offers, reported in that offer's
     * own currency. When the offers are priced in more than one currency,
     * totals are compared by their exact (unrounded) amount in the comparison
     * currency at the dated rate valid at `$now`; a total whose currency has
     * no known rate cannot be compared and is skipped. Ties keep the listing
     * order (the first offer wins), as in the single-currency case.
     *
     * @param  list<ComparedOffer>  $public  already in listing order
     * @param  array<int, numeric-string|null>  $amounts  keyed by offer id
     */
    private function lowestTotal(array $public, array $amounts): ?Money
    {
        $lowest = null;
        $lowestAmount = null;

        foreach ($public as $offer) {
            $amount = $amounts[$offer->offerId];

            if ($amount !== null && ($lowestAmount === null || bccomp($amount, $lowestAmount, self::COMPARISON_SCALE) < 0)) {
                $lowest = $offer->price->total;
                $lowestAmount = $amount;
            }
        }

        return $lowest;
    }

    /**
     * Each offer's total as a comparable amount, keyed by offer id: its own
     * minor units when all offers share one currency (no rate needed), else
     * minor units of the comparison currency (null when no rate is known).
     *
     * @param  list<ComparedOffer>  $public
     * @return array<int, numeric-string|null>
     */
    private function comparableAmounts(array $public, ?ComparisonRates $rates): array
    {
        $single = count(self::currenciesOf($public)) <= 1;
        $amounts = [];

        foreach ($public as $offer) {
            $amounts[$offer->offerId] = $single ? (string) $offer->price->total->minor : $rates?->exactMinor($offer->price->total);
        }

        return $amounts;
    }

    /**
     * @param  list<ComparedOffer>  $offers
     * @return list<string>
     */
    private static function currenciesOf(array $offers): array
    {
        return array_values(array_unique(array_map(static fn (ComparedOffer $offer): string => $offer->price->total->currency, $offers)));
    }

    /**
     * Compares two exact amounts; an unknown amount (null) sorts last.
     *
     * @param  numeric-string|null  $a
     * @param  numeric-string|null  $b
     */
    private static function compareAmounts(?string $a, ?string $b): int
    {
        if ($a === null || $b === null) {
            return ($a === null) <=> ($b === null);
        }

        return bccomp($a, $b, self::COMPARISON_SCALE);
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
