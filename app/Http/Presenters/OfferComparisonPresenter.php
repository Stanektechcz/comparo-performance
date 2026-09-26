<?php

namespace App\Http\Presenters;

use App\Domain\Compliance\ComplianceDecision;
use App\Domain\Merchants\Trust\TrustScore;
use App\Domain\Offers\Queries\ActiveRankingWeights;
use App\Domain\Offers\Queries\ComparedOffer;
use App\Domain\Offers\Queries\OfferComparison;
use App\Domain\Offers\Queries\ProductOfferComparison;
use App\Domain\Offers\Ranking\RankingPart;
use App\Domain\Offers\Ranking\RankingPenalty;
use App\Domain\Offers\Ranking\RankingResult;
use App\Domain\Platform\Cache\CacheKeys;
use App\Domain\Platform\Cache\CatalogCacheVersion;
use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Shared\Money;
use App\Models\Offer;
use App\Models\Product;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Serializes an offer comparison. Every field is whitelisted here; nothing is
 * passed through from Eloquent. Hidden rank penalties, risk and the internal
 * `ranked` list are never serialized. Results are cached per product × market
 * × currency × ranking version × product version (docs: cache key catalogue).
 */
final class OfferComparisonPresenter
{
    /**
     * Cache format tokens: bumped whenever a presented shape changes, so an
     * entry cached before a deploy is never served in the old shape.
     */
    private const string PAGE_FORMAT = 'page-v3';

    private const string API_FORMAT = 'api-v1-r2';

    public function __construct(
        private readonly ProductOfferComparison $comparison,
        private readonly CatalogCacheVersion $versions,
        private readonly ActiveRankingWeights $weights,
    ) {}

    /**
     * `topEligibleTotal` is the best-buy-eligible offer's landed total in that
     * offer's own currency (server-side only, for price intelligence).
     *
     * `$compliance`, when given, must be what `ComplianceResolver::decide()`
     * would return for this product, market and `$now` — e.g. one entry of a
     * caller's own `decideMany()` batch — so the cached payload is identical
     * either way; the cache key does not vary by who resolved it. Passing it
     * skips a redundant per-product compliance query when the caller already
     * resolved compliance for a whole page.
     *
     * @return array{compliance: array<string, mixed>, offers: list<array<string, mixed>>, offerSummary: array<string, mixed>, topEligibleTotal: Money|null}
     */
    public function forPage(Product $product, MarketContext $market, DateTimeImmutable $now, ?ComplianceDecision $compliance = null): array
    {
        $page = $this->cached($product, $market, $now, self::PAGE_FORMAT, $compliance, fn (OfferComparison $comparison): array => [
            'compliance' => self::compliance($comparison->compliance),
            'offers' => array_map(fn (ComparedOffer $offer): array => $this->offerRow($offer, $comparison), $comparison->offers),
            'offerSummary' => [
                'total' => $comparison->totalOffers,
                'shown' => count($comparison->offers),
                'withheldFlagged' => $comparison->withheldFlagged,
                'notShipping' => $comparison->notShipping,
                'shippingUnavailable' => $comparison->shippingUnavailable,
                'lowestTotal' => MoneyPresenter::present($comparison->lowestTotal),
                'bestValueOfferId' => $comparison->bestValueOfferId,
            ],
            // Cached as scalars: stores that serialize values refuse objects (cache.serializable_classes = false).
            'topEligibleTotal' => self::moneyScalars($this->topEligibleTotal($comparison)),
        ]);

        $page['topEligibleTotal'] = self::moneyFromScalars($page['topEligibleTotal']);

        return $page;
    }

    /**
     * @return array{minor: int, currency: string}|null
     */
    private static function moneyScalars(?Money $money): ?array
    {
        return $money === null ? null : ['minor' => $money->minor, 'currency' => $money->currency];
    }

    private static function moneyFromScalars(mixed $scalars): ?Money
    {
        if (! is_array($scalars) || ! is_int($scalars['minor'] ?? null) || ! is_string($scalars['currency'] ?? null)) {
            return null;
        }

        return Money::of($scalars['minor'], $scalars['currency']);
    }

    /**
     * Public API v1 shape (snake_case), see API-ENDPOINTS.md "GET /products/{slug}/offers".
     *
     * `meta.market_min` is in `meta.market_min_currency`: the offers' own
     * currency when they share one, the comparison currency (`meta.currency`)
     * when they span several; both are null without a market minimum.
     *
     * `$compliance` has the same precondition as {@see self::forPage()}.
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function forApi(Product $product, MarketContext $market, DateTimeImmutable $now, ?ComplianceDecision $compliance = null): array
    {
        return $this->cached($product, $market, $now, self::API_FORMAT, $compliance, fn (OfferComparison $comparison): array => [
            'data' => array_map(fn (ComparedOffer $offer): array => $this->apiOffer($offer, $comparison), $comparison->offers),
            'meta' => [
                'country' => $market->code,
                'currency' => (string) config('comparo.comparison_currency'),
                'display_currency' => $market->currency,
                'market_min' => $comparison->marketStats->minTotalMinor ?: null,
                'market_min_currency' => $comparison->marketStats->minTotalMinor > 0 ? $comparison->marketStats->currency : null,
                'compliance' => $comparison->compliance->status->value,
                'purchasable' => $comparison->isPurchasable(),
                'ranking_version' => $comparison->weights->version,
                'evaluated_at' => $comparison->evaluatedAt->format(DateTimeInterface::ATOM),
                'excluded' => [
                    'does_not_ship' => $comparison->notShipping,
                    // Blocked products reveal nothing derived from withheld offers — not even a count.
                    'compliance_blocked' => $comparison->compliance->status->offersVisible() ? 0 : null,
                    'price_anomaly' => $comparison->withheldFlagged,
                    // Ships to the market, but the shipping cost has no known rate into the offer currency.
                    'shipping_unavailable' => $comparison->shippingUnavailable,
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function compliance(ComplianceDecision $decision): array
    {
        return [
            'status' => $decision->status->value,
            'label' => $decision->status->label(),
            'reason' => $decision->reason,
            'source' => $decision->source,
            'reviewedAt' => $decision->reviewedAt?->format(DateTimeInterface::ATOM),
            'offersVisible' => $decision->status->offersVisible(),
            'purchasable' => $decision->status->isPurchasable(),
            'recommendable' => $decision->status->isRecommendable(),
            'underReview' => $decision->status->requiresReview(),
        ];
    }

    /**
     * @return array{score: int, label: string, band: string, signals: list<array{label: string, value: string, percent: int, good: bool}>}
     */
    public static function trust(TrustScore $trust): array
    {
        return ['score' => $trust->score, 'label' => $trust->label, 'band' => $trust->band(), 'signals' => $trust->publicSignals];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rank(RankingResult $rank): array
    {
        return [
            'score' => $rank->score,
            'band' => $rank->band->value,
            'label' => $rank->band->label(),
            'parts' => array_map(static fn (RankingPart $part): array => $part->toArray(), $rank->parts),
            'penalties' => array_map(static fn (RankingPenalty $penalty): array => $penalty->toArray(), $rank->penalties),
            'withheldChecks' => $rank->hiddenPenaltyCount,
            'eligibleBestBuy' => $rank->eligibleBestBuy,
            'version' => $rank->weights->version,
            'evaluatedAt' => $rank->evaluatedAt->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @template T of array
     *
     * @param  Closure(OfferComparison): T  $present
     * @return T
     */
    private function cached(Product $product, MarketContext $market, DateTimeImmutable $now, string $format, ?ComplianceDecision $compliance, Closure $present): array
    {
        $key = CacheKeys::offerComparison(
            $product->id,
            $market->code,
            $market->currency,
            $this->weights->current()->version,
            $this->versions->forProduct($product->id),
            $format,
        );

        $hit = Cache::get($key);
        if (is_array($hit) && ($hit['valid_until'] ?? 0) > $now->getTimestamp()) {
            return $hit['payload'];
        }

        $comparison = $this->comparison->compare($product, $market, $now, $compliance);
        $payload = $present($comparison);
        Cache::put($key, ['valid_until' => $comparison->validUntil->getTimestamp(), 'payload' => $payload], $comparison->validUntil);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function offerRow(ComparedOffer $offer, OfferComparison $comparison): array
    {
        $price = $offer->price;
        $currency = $price->total->currency;

        return [
            'id' => $offer->offerId,
            'merchant' => [
                'name' => $offer->merchantName,
                'slug' => $offer->merchantSlug,
                'verified' => $offer->merchantVerified,
                'trust' => self::trust($offer->trust),
            ],
            'variantLabel' => $offer->variantLabel,
            'packLabel' => $offer->packLabel,
            'availability' => ['key' => $offer->availability->value, 'label' => $offer->availability->label()],
            'price' => [
                'base' => MoneyPresenter::present($price->basePrice),
                'discount' => MoneyPresenter::present($price->discount),
                'effective' => MoneyPresenter::present($price->effectivePrice),
                'shipping' => MoneyPresenter::present($price->shipping),
                'shippingBasis' => $price->shippingBasis->value,
                'total' => MoneyPresenter::present($price->total),
                'freeShippingThreshold' => MoneyPresenter::present($price->freeShippingThreshold),
                'coupon' => $price->coupon === null ? null : [
                    'code' => $price->coupon->code,
                    'title' => $price->coupon->title,
                    'type' => $price->coupon->type->value,
                    'stateLabel' => $price->coupon->state->label(),
                    'exclusive' => $price->coupon->exclusive,
                    'saving' => MoneyPresenter::present(Money::of($price->coupon->savingMinor, $currency)),
                ],
                'deliveryDays' => $price->deliveryMaxDays === null ? null : [$price->deliveryMinDays, $price->deliveryMaxDays],
                'carrier' => $price->carrier,
                'displayTotal' => MoneyPresenter::present($offer->displayTotal),
            ],
            'referencePrice' => $offer->referencePriceMinor === null ? null : [
                'amount' => MoneyPresenter::present(Money::of($offer->referencePriceMinor, $currency)),
                'discountPercent' => $offer->verifiedDiscountPercent(),
                'verified' => ! $offer->referencePriceUnverified,
            ],
            'rank' => self::rank($offer->rank),
            'priceConfidence' => [
                'score' => $offer->priceConfidence->score,
                'level' => $offer->priceConfidence->level,
                'signals' => $offer->priceConfidence->signals,
            ],
            'updatedAt' => $offer->sourceUpdatedAt->format(DateTimeInterface::ATOM),
            'freshnessHours' => round($offer->freshnessHours, 1),
            'purchaseUrl' => $comparison->isPurchasable() ? self::safeOutboundUrl($offer->url) : null,
            'isBestValue' => $offer->offerId === $comparison->bestValueOfferId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function apiOffer(ComparedOffer $offer, OfferComparison $comparison): array
    {
        $price = $offer->price;

        return [
            'offer_id' => $offer->offerId,
            'merchant' => [
                'name' => $offer->merchantName,
                'slug' => $offer->merchantSlug,
                'trust_score' => $offer->trust->score,
                'verified' => $offer->merchantVerified,
            ],
            'price' => ['amount' => $price->basePrice->minor, 'currency' => $price->basePrice->currency],
            'shipping' => [
                'amount' => $price->shipping->minor,
                'free_over' => $price->freeShippingThreshold?->minor,
                'basis' => $price->shippingBasis->value,
            ],
            'coupon' => $price->coupon === null ? null : [
                'code' => $price->coupon->code,
                'type' => $price->coupon->type->value,
                'state' => $price->coupon->state->value,
                'saving' => $price->coupon->savingMinor,
            ],
            'total' => ['amount' => $price->total->minor, 'currency' => $price->total->currency],
            'display_total' => $offer->displayTotal === null ? null : ['amount' => $offer->displayTotal->minor, 'currency' => $offer->displayTotal->currency],
            'availability' => $offer->availability->value,
            'delivery' => $price->deliveryMaxDays === null ? null : ['min_days' => $price->deliveryMinDays, 'max_days' => $price->deliveryMaxDays],
            'comparo_rank' => [
                'score' => $offer->rank->score,
                'label' => $offer->rank->band->label(),
                'version' => $offer->rank->weights->version,
                'breakdown' => array_map(static fn (RankingPart $part): array => [
                    'factor' => $part->label,
                    'key' => $part->key,
                    'points' => $part->points,
                    'maximum' => $part->maximum,
                ], $offer->rank->parts),
                'penalties' => array_map(static fn (RankingPenalty $penalty): array => $penalty->toArray(), $offer->rank->penalties),
                'withheld_checks' => $offer->rank->hiddenPenaltyCount,
            ],
            'flags' => ['reference_price_unverified' => $offer->referencePriceUnverified],
            'price_confidence' => $offer->priceConfidence->score,
            'best_value' => $offer->offerId === $comparison->bestValueOfferId,
            'purchase_url' => $comparison->isPurchasable() ? self::safeOutboundUrl($offer->url) : null,
        ];
    }

    private function topEligibleTotal(OfferComparison $comparison): ?Money
    {
        foreach ($comparison->offers as $offer) {
            if ($offer->rank->eligibleBestBuy) {
                return $offer->price->total;
            }
        }

        return null;
    }

    /**
     * Merchant URLs come from feeds (untrusted): only absolute http(s) URLs are ever emitted.
     */
    private static function safeOutboundUrl(string $url): ?string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }
}
