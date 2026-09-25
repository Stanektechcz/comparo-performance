# Module: Pricing

Namespace `App\Domain\Pricing` (+ `App\Domain\Shared\Money`, `JsMath`). ADRs: 0003, 0009, 0010.

## Responsibilities

- Landed price per offer and market: price − best applicable coupon + shipping (free-shipping rules).
- Market price baseline per product × market (min total, median total, shipping median).
- Price-history statistics, badge, buy-timing and trend over a daily-low series.
- Price confidence per offer; unverified reference price ("fake discount") flag.
- Coupon vocabulary and state.

Owned tables: `coupons`, `coupon_country`, `price_snapshots` (append-only), `market_price_stats`.

## Public services

All services are pure: no DB, no clock, no container. Time is an argument where needed.

| Service | Method | Input | Output |
|---|---|---|---|
| `LandedPrice\LandedPriceCalculator` | `calculate(LandedPriceInput, DateTimeImmutable $now)` | `priceMinor`, `currency`, `marketCode`, `priceFlagged`, `?ShippingTerms` (cost, currency, min/max days, carrier; null = does not ship), `?freeShippingThresholdMinor`, `CouponTerms[]` | `LandedPrice`: `ships`, `basePrice`, `?coupon` (`AppliedCoupon`), `discount`, `effectivePrice`, `shipping`, `shippingBasis`, `total`, `freeShippingThreshold`, delivery days, carrier (all `Money`) |
| `MarketStats\MarketStatsCalculator` | `calculate(MarketListing[])` | per listing: `priceMinor`, `?shippingCostMinor`, `?freeShippingThresholdMinor` | `MarketStats`: `minTotalMinor`, `medianTotalMinor`, `shippingMedianMinor`, `count` |
| `History\PriceHistoryAnalyzer` | `stats(int[] $dailyLows)` | daily-low series, oldest first (≥ 1 value) | `PriceHistoryStats`: averages 7/30/90/365, low, high, median, low30, low90, `volatilityPercent`, current, days |
| | `badge(int $current, PriceHistoryStats)` | | `{key, label, explanation}`: exceptional / good / typical / above |
| | `timing(int $current, PriceHistoryStats)` | | strong / reasonable / wait / poor |
| | `trend(int[] $dailyLows, PriceHistoryStats)` | | volatile / down / up / stable |
| | `hasUnverifiedReferencePrice(?int $reference, int $historyMedian)` | | `bool` (reference ≥ 1.25 × median, integer comparison) |
| `Confidence\PriceConfidenceService` | `evaluate(PriceConfidenceInput)` | `ageHours`, `merchantVerified`, `priceAnomaly`, `priceMinor`, `hasAvailability`, `hasShippingZones`, `linkHealthy` | `PriceConfidence`: `score` 0–100, `level` (High ≥ 88, Moderate ≥ 70, Low ≥ 50, Unreliable), `signals[]`, `ageHours` |

Query layer (reads the DB; not pure):

| Class | Method | Output |
|---|---|---|
| `Queries\ProductPriceHistory` | `dailyLows(int $productId, DateTimeImmutable $until, string $market = 'ALL', int $days = 365)` | daily-low series from `market_price_stats` |
| `Currency\ExchangeRates` | `conversion(string $from, string $to, DateTimeImmutable $at)` | `?CurrencyConversion` from dated `exchange_rates` |
| `Currency\CurrencyConversion` | `convert(Money)` | `Money` in the target currency (display only) |

Enums: `CouponType` (percent, fixed, free_shipping), `CouponState` (verified, merchant, community,
unverified, expired, invalid; `isUsable()`, `label()`), `PriceAnomaly` (too_low, too_high),
`LandedPrice\ShippingBasis` (not_shipping_to_market, zone_rate, free_over_threshold, free_shipping_coupon),
`History\SnapshotReason`, `History\SnapshotSource`.

## Landed-price rules

| Rule | Detail |
|---|---|
| Coupon applicability | market listed, `starts_at` null or ≤ now, `ends_at` > now, state usable, price ≥ `min_order` (`CouponTerms::isApplicable`) |
| Best coupon | highest saving; first wins on ties; none when `priceFlagged` (anomaly) |
| Percent | basis points on the raw price, scale 10 000, half-up to the minor unit once |
| Free-shipping coupon | worth the zone rate only while the raw price is under the threshold |
| Free-shipping threshold | tested against the coupon-reduced price for the row total |
| Not shipping to market | `ships = false`, shipping 0, basis `not_shipping_to_market` |
| Currency | shipping and fixed coupons must already be in the offer currency, else `InvalidArgumentException` |

## Invariants

- Money is integer minor units + ISO-4217 (`Money`); no floats in amounts.
- `price_snapshots` is never updated or deleted (DB triggers + `AppendOnly`); corrections are new rows
  with `corrects_snapshot_id`.
- `market_price_stats.source` distinguishes `aggregated` from `prototype_demo`.
- Market baseline uses raw prices (no coupons) and includes anomalous offers (preserved quirk).

## Parity status

| Capability | Cases | Status |
|---|---|---|
| Landed price, coupon choice, shipping | 7 209 offer × market | passing |
| Market baseline | 1 242 product × market | passing |
| History stats, badge, timing, trend | 46 products | passing |
| Price confidence, unverified reference price | 267 offers | passing |
| Deal Score, basket optimiser, forecast | — | not ported |

Deviations: exact integer arithmetic; future `starts_at` blocks a coupon (ADR-0010).
