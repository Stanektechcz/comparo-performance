# Module: Compliance

Namespace `App\Domain\Compliance`. ADRs: 0007, 0008. Contradiction block: C-05. Open decision: D-11.

## Responsibilities

- Hold the legal status of each product per market (`product_compliance_rules`, `unique(product_id, country_id)`).
- Resolve a status for any product × market, including the no-rule case.
- Define what each status allows on every surface; enforce it before serialization.
- Feed the staff review queue with `unknown` product × market pairs.

## Public API

| Class | Member | Contract |
|---|---|---|
| `ComplianceStatus` (enum) | `allowed`, `restricted`, `prescription_only`, `not_allowed`, `unknown` | |
| | `offersVisible()` | true for allowed, restricted, unknown (unknown: information only) |
| | `isPurchasable()` | true for allowed, restricted |
| | `isRecommendable()` | true for allowed only |
| | `requiresReview()` | true for unknown |
| | `isBlocked()` | true for prescription_only, not_allowed |
| | `label()` | "Allowed", "Restricted", "Prescription only", "Not allowed", "Not yet reviewed" |
| `ComplianceDecision` | `status`, `marketCode`, `reason`, `source`, `reviewedAt`, `hasExplicitRule` | Immutable |
| | `unreviewed(string $marketCode)` | `unknown`, `hasExplicitRule = false` — used when no rule exists |
| | `toArray()` | adds `label`, `offers_visible`, `purchasable`, `recommendable`, `under_review` |
| `App\Models\ProductComplianceRule` | `product`, `country`, `reviewer` relations | Persistence |
| `Queries\ComplianceResolver` | `decide(int $productId, MarketContext)`, `decideMany(int[] $productIds, MarketContext)` | Missing rule → `ComplianceDecision::unreviewed()` |
| Enforcement | `ProductOfferComparison` returns no offers unless `offersVisible()`; `OfferComparisonPresenter` emits `purchaseUrl` / `purchase_url` only when `isPurchasable()`; best value only when `isRecommendable()` | exists |
| Review queue (staff) | list `unknown` product × market pairs | planned (Phase 8) |

## Policy matrix

| Status | Offers in props/API | Purchase links, `/go` | Best buy, recommendations, alerts, sponsorship | Review queue |
|---|---|---|---|---|
| allowed | yes | yes | yes | — |
| restricted | yes + warning | yes | no | — |
| unknown | prices for information only | **no** | no | yes |
| prescription_only | none (informational page) | no | no | — |
| not_allowed | none (informational page) | no | no | — |

Ranking receives `complianceUnknown` (−6 penalty, not best-buy eligible) and `complianceBlocked`
(not eligible) for parity; ranking never decides visibility.

## Invariants

- A missing rule is `unknown`, never `allowed`.
- The decision is applied server-side before any serialization: Inertia props, API resources, caches,
  search documents, sitemaps, JSON-LD, notifications, redirect.
- Every compliance-sensitive cache key contains the market.
- Rule changes require `compliance.manage`, write `audit_logs`, and emit `ComplianceRuleChanged` (planned).
- Demo import (`Platform\PrototypeImport\PrototypeSnapshotImporter::DEFAULT_RULE_SOURCE`) writes explicit
  `allowed` rules with source "Default policy (prototype demo import)"; production never creates implicit rules.

## Parity status

| Item | Status |
|---|---|
| `compliance.json` (1 242 product × market cases) | exported; **not a parity target** — policy deviates intentionally (ADR-0010 deviations 3 and 4) |
| Ranking penalty/eligibility for unknown/blocked | covered by ranking parity (3 239 cases) |
| Status × surface feature test matrix | planned (Phase 1 exit criterion) |
