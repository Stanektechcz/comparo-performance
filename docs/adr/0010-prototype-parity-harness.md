# ADR-0010: Prototype parity harness

- Status: Accepted
- Date: 2026-09-25
- Related: ADR-0001, ADR-0004, ADR-0007, ADR-0009, [scoring-engines-map.md](../architecture/scoring-engines-map.md) §13

## Context

The prototype's engines are the specification. A hand port drifts silently: JS `Math.round` differs from
PHP `round`, `||` fallbacks treat `0` as missing, sorts are stable, and several engines exist twice in
the prototype (C-09, C-10). Scores are published with explanations, so a one-point drift is a visible bug.

## Decision

1. **Exporter.** `tools/prototype-parity/export-fixtures.mjs` loads the prototype read-only in a
   `node:vm` sandbox with a frozen clock (`2026-09-06T09:00:00Z`), in the HTML's script order minus
   `support.js` (DOM runtime) and `live.js` (non-deterministic). It evaluates the HTML's Component class
   rather than hand-copying embedded logic.
2. **Outputs (byte-stable:** no wall-clock timestamps, stable ordering, one record per line):
   - `tests/Fixtures/PrototypeParity/{ranking,pricing,compliance,trust,products,reviews,matching,dosing,delivery}.json`
   - `database/data/prototype/seed-snapshot.json` — the full materialised seed graph including `derived`
     ratings, used by the demo importer.
3. **Drift check.** `node tools/prototype-parity/export-fixtures.mjs --check` exits non-zero if any file
   would change. It belongs in CI (not yet wired into `.github/workflows/tests.yml`).
4. **PHP parity tests** (`tests/Unit/Parity/*`, helper `tests/Support/PrototypeFixtures.php`) must pass:

   | Engine | PHP class | Cases | Test |
   |---|---|---|---|
   | ComparoRank | `Offers\Ranking\RankingService` | 3 239 real offer × market + 21 synthetic | `RankingParityTest` |
   | Landed price | `Pricing\LandedPrice\LandedPriceCalculator` | 7 209 offer × market | `LandedPriceParityTest` |
   | Market price baseline | `Pricing\MarketStats\MarketStatsCalculator` | 1 242 product × market | `LandedPriceParityTest` |
   | Trust Score 2.0 | `Merchants\Trust\TrustService` | 13 merchants | `MerchantScoresParityTest` |
   | Risk score/level | `Merchants\Risk\RiskService` | 13 merchants | `MerchantScoresParityTest` |
   | Catalogue completeness | `Catalog\Completeness\ProductCompletenessService` | 46 products | `ProductScoresParityTest` |
   | Price-history stats, badge, timing, trend | `Pricing\History\PriceHistoryAnalyzer` | 46 products | `ProductScoresParityTest` |
   | Price confidence, unverified reference price | `Pricing\Confidence\PriceConfidenceService`, `PriceHistoryAnalyzer` | 267 offers | `ProductScoresParityTest` |

   Exported but **not yet ported**: `reviews.json`, `matching.json`, `dosing.json`, `delivery.json`.
5. **Sensitivity proof.** A mutation check (one ranking weight +1) produced 426 mismatches, so the tests
   detect small changes.
6. **Change rule.** To change a ported algorithm: (a) prove parity with the current fixtures first,
   (b) write an ADR describing the change and its expected effect, (c) change code and regenerate or
   amend fixtures in the same change, with the diff reviewed. Fixtures are never edited to make a
   failing test pass without (b).

### Intentional deviations (parity does not apply, or applies with zero observed mismatches)

| # | Deviation | Prototype | Port | Evidence |
|---|---|---|---|---|
| 1 | Landed-price arithmetic | binary floats | exact integers, half-up to the minor unit (ADR-0009) | 0 mismatches over 7 209 cases |
| 2 | Coupon start date | `starts` ignored | coupon with future `starts_at` is not applicable (`CouponTerms::isApplicable`) | no seed coupon affected |
| 3 | Missing compliance rule | `allowed` ("Default policy") | `unknown`; demo import materialises explicit `allowed` rows labelled "Default policy (prototype demo import)" | ADR-0007 |
| 4 | Compliance policy per status | `unknown` offers purchasable | allowed / restricted / unknown / blocked matrix of ADR-0007 | ADR-0007 |

### Preserved prototype quirks (parity first, improvement later via ADR)

| Quirk | Where | Effect |
|---|---|---|
| `marketMin` built from raw prices including anomalous offers | `MarketStatsCalculator` | An anomalous low price lowers every other offer's price score |
| `||` falsy fallbacks | `RankingService`, `TrustService`, `PriceConfidenceService` | Freshness `0 h` scored as `12 h`; complaint rate `0 %` scored as `2 %`; trust `0` → 60, delivery `0` → 6 days, rating `0` → 4.0 |
| Upper median (`values[floor(n/2)]`) | `JsMath::upperMedian` | Even-length medians differ from the statistical median |
| JS `Math.round` (half toward +∞) | `JsMath::round` | Negative halves round up, unlike PHP `round` |
| Stable sort of explanation parts | `RankingService` (`usort` stable since PHP 8.0, like `Array.prototype.sort`) | Parts with equal points keep factor order |

## Consequences

- Every scoring port lands with a fixture-backed test; CI catches both PHP drift and prototype drift.
- The prototype must stay byte-identical; its SHA-256 hashes are checked after each migration step.
- Parity tests use the frozen time `PrototypeFixtures::now()`; production passes real evaluation time.
- Improving a quirk is a normal ADR-driven change, not a bug fix.

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| Hand-written expected values | Few cases, no coverage of edge combinations, no drift detection |
| Headless browser (Playwright) export | Heavier, slower, and non-deterministic timers; `node:vm` is enough |
| Port and "fix" known quirks at once | No way to tell a deliberate change from a porting error |
