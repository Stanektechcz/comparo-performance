# Module: Ranking (ComparoRank)

Namespace `App\Domain\Offers\Ranking`. ADRs: 0004, 0010. Prototype source: `intel.js rank()`
([scoring-engines-map.md](../architecture/scoring-engines-map.md) §1).

## Responsibilities

- Score each offer 0–100 for organic ordering within one product and market.
- Produce an explanation (parts, visible penalties, withheld-check count, version, evaluatedAt).
- Decide best-buy eligibility.

Owned tables: `ranking_versions` (single active row), `ranking_weights`.

## Public API

| Class | Member | Contract |
|---|---|---|
| `RankingService` | `rank(RankingContext, RankingWeights, DateTimeImmutable $evaluatedAt): RankingResult` | Pure |
| `RankingContext` | `totalMinor`, `marketMinTotalMinor`, `shippingMinor`, `marketShippingMedianMinor`, `deliveryDaysMax`, `merchantRating`, `merchantReviewCount`, `merchantTrustScore`, `freshnessHours`, `?availability`, `hasValidCoupon`, `completeness`, `priceAnomaly`, `unverifiedReferencePrice`, `outboundLinkProblem`, `complianceUnknown`, `complianceBlocked`, `riskLevel` | Closed; no commercial field |
| `RankingWeights` | `fromArray($version, $weights)`, `prototypeDefaults()` (`prototype-v1`), `withOverrides()`, `sum()`, `toArray()` | Rejects unknown factors and negative weights |
| `RankingFactor` | `price, trust, delivery, reviews, freshness, availability, shipping` | Closed enum with labels |
| `RankingResult` | `score`, `band`, `parts`, `penalties` (visible only), `hiddenPenaltyCount`, `eligibleBestBuy`, `weights`, `evaluatedAt`; `toArray()` adds `version`, `withheld_checks`, `evaluated_at` | Immutable |
| `RankBand` | exceptional ≥ 90, excellent ≥ 80, good ≥ 70, fair ≥ 60, low | |
| `App\Models\RankingVersion` | `toRankingWeights()` | Converts a version row to `RankingWeights` |
| `Offers\Queries\ActiveRankingWeights` | `current(): RankingWeights` | Loads the active version (query layer) |
| `Offers\Queries\ProductOfferComparison` | `compare(Product, MarketContext, DateTimeImmutable $now, ?ComplianceDecision)` | Builds contexts, ranks, filters publishable offers, sorts |

## Formula (prototype-v1)

| Factor | Weight | Sub-score (0–1) |
|---|---|---|
| price | 30 | 0 if anomaly; else `clamp(1 − (total / marketMin − 1) / 0.35)` |
| trust | 20 | `(trust ?: 60) / 100` |
| delivery | 14 | `clamp(1 − ((deliveryDaysMax ?: 6) − 2) / 8)` |
| reviews | 12 | `clamp(((rating ?: 4.0) − 3) / 2) × clamp(0.55 + log10(1 + (reviewCount ?: 50)) / 6)` |
| freshness | 10 | `clamp(1 − (freshnessHours ?: 12) / 72)` |
| availability | 8 | in_stock 1.0, low_stock 0.7, preorder 0.35, out_of_stock / null 0 |
| shipping | 6 | `clamp(1 − shipping / (shippingMedian × 2))`, median default 500 minor |

Weights are renormalised to 100. Plus **offer quality** up to 7: `(completeness ?: 0.8) × 5 + 2` if a valid
coupon exists.

| Penalty | Points | Visible |
|---|---|---|
| Stale data (freshness > 48 h) | −10 | yes |
| Price under review (anomaly) | −14 | yes |
| Unverified reference price | −8 | yes |
| Market status not verified (compliance unknown) | −6 | yes |
| Outbound link problem | −10 | no (counted) |
| Integrity signals, risk HIGH / CRITICAL | −6 / −14 | no (counted) |

Final score: JS-rounded, clamped 0–100. Best buy: no anomaly, not compliance-blocked or unknown, score ≥ 60.
Ordering (in `Offers\Queries\ProductOfferComparison`): score desc, total asc, offer id asc. Explanation parts
are sorted by points desc with a stable sort.

## Invariants

- No commission, plan, tier, partner, sponsorship or spend input — ever (`tests/Architecture/RankingPurityTest.php`).
- Every explanation names its version and evaluation time.
- Hidden penalties are counted, never itemised.
- Rank depends on all offers of the product in the market; cache invalidation is per product × market.
- Weight changes are new `ranking_versions` rows, activated with `ranking.configure` and audited.

## Parity status

| Fixture | Cases | Status |
|---|---|---|
| `ranking.json` offers | 3 239 offer × market | passing |
| `ranking.json` synthetic edge cases | 21 | passing |
| Default weights equal prototype | 1 | passing |
| Sensitivity (one weight +1) | 426 mismatches | detected |

Preserved quirks: `||` fallbacks (0 treated as missing), `marketMin` from raw prices incl. anomalies.
