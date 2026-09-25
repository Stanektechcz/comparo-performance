# Module: Matching

Namespace `App\Domain\Matching`. ADRs: 0012, 0015. Design: `docs/architecture/phase-2-feeds-matching.md`.

## Responsibilities

- Score a feed listing against candidate canonical products (pure engine, ported from intel.js Engine 2.0).
- Narrow candidates before scoring (EAN ∪ brand/alias ∪ brand-in-title).
- Record every match decision, append-only, in a linear supersession chain.
- Run the merchant and staff review queues for suggested/unmatched/conflicted listings.
- Hold a match when the matched product is blocked/prescription-only in the listing's market.
- Collect new-product proposals for listings that match nothing (never auto-creates products).

Owned tables: `matching_policies`, `matching_decisions`, `matching_conflicts` (+
`matching_conflict_values`), `product_candidates` (+ `product_candidate_sources`), `brand_aliases`.
Matching reads `merchant_products`, `products`, `ingredient_product.is_listed` and Compliance's
read-only contract; it never writes `offers` directly (that stays `Offers\Actions`).

## Engine (pure)

| Class | Role |
|---|---|
| `Engine\ProductMatcher` | scores one `FeedItemFacts` against one `CandidateProduct`, returns `MatchResult` (score, `MatchLevel`, `MatchPart[]`, `MatchSignal[]`) |
| `Engine\MatchingPolicy` | versioned weights/thresholds/levels; `prototypeV1()` = intel.js Engine 2.0 |
| `Engine\BrandAliasSet`, `MatchPartLabel` | brand-alias lookup and human-readable part labels |
| `Shared\Text\TextFold`, `TitleSimilarity` | folding/tokenising and the title-similarity signal |

No DB access, no clock reads — the same call the queued `MatchFeedItems` job makes is what
`tests/Unit/Parity/MatchingParityTest.php` and `tests/Feature/Parity/DatabaseMatchingParityTest.php`
run against the prototype fixtures.

### Weights (policy `prototype-v1`)

| Signal | Points |
|---|---|
| `ean_exact` | 50 |
| `brand_exact` | 15 |
| `brand_in_title` | 9 |
| `title_similarity_scale` | 22 (× 0–1 similarity) |
| `pack_exact` | 10 |
| `pack_alternate` | 6 |
| `pack_differs` | −12 |
| `variant` | 7 |
| `ingredient` | 5 |

Thresholds: `auto` ≥ 90, `review` (confirm) 65–89, below 65 = `unmatched` (`MatchBucket`). Levels
(`MatchLevel`, for display): `exact` 100, `very_high` 90, `high` 80, `possible` 65, else `manual_review`.

## Candidate narrowing

`Matching\Queries\CandidateProducts` narrows to EAN-exact ∪ brand-or-alias ∪ brand-in-title matches,
ordered by product id, before scoring — a deliberate deviation from the prototype (which scores every
product); see ADR-0012 §4 for the ≤44-point bound that proves buckets are unchanged.

## Decisions and buckets

| Bucket | Score | Outcome |
|---|---|---|
| `auto` | ≥ 90 | published automatically when `matching-auto-publish` is on |
| `confirm` | 65–89 | queued for merchant/staff review, not published |
| `unmatched` | < 65 | no candidate accepted; only creates/updates a `product_candidates` proposal on explicit action |

Every decision — auto, suggested, manual, rejected, rematch, unlinked (`MatchDecisionKind`) — writes one
append-only `matching_decisions` row, chained by `supersedes_id`. `ListingMatchStatus` on
`merchant_products` (`unmatched`, `suggested`, `auto`, `manual`, `compliance_hold`, `rejected`) is the
current-state projection of that history.

## Actions

| Class | Purpose | Audited |
|---|---|---|
| `MatchListing` | run the engine for one feed item, write the resulting decision | yes |
| `DecideMatch` | merchant/staff confirms, chooses an alternative, or rejects a `confirm`-bucket suggestion | yes |
| `Rematch` | staff re-links a listing, including a previously **published** one (`offers.manage`) | yes |
| `ManualLink` | direct link without going through the engine (staff) | yes |
| `ProposeProductCandidate` / `ResolveProductCandidate` | merchant proposes a new product; staff approves/rejects/merges | yes |
| `ResolveConflict` | staff resolves a `matching_conflicts` row | yes |
| `ComplianceHolds` | checks `Matching\Contracts\ComplianceHoldCheck` (implemented by `Compliance\MarketComplianceHold`) before allowing publish | — |

## Review queues

- Merchant: `merchant.matching.{index,suggested,unmatched,history}` — scoped to the active merchant.
- Staff: `admin.catalogue.matching.*` — all merchants, gated by `matching.review` (+ `offers.manage` to
  relink a published listing).

## Invariants

- `matching_decisions` is append-only: an Eloquent guard and DB triggers reject any `UPDATE`/`DELETE`
  (`Platform\Exceptions\AppendOnlyViolation`).
- `Matching\Engine` depends on nothing but `Shared`; `Matching` never depends on `Feeds` (the reverse is
  allowed); `Matching → Compliance` only through `Matching\Contracts\ComplianceHoldCheck`.
- No canonical `products` row is ever created automatically from a match or a candidate — that stays a
  staff catalogue action, deferred in full to Phase 8 (A-15).
- Compliance hold applies only to products blocked or prescription-only in the feed's market;
  `unknown` (unreviewed) does not hold the match (A-19).

## Known gaps

- Canonical product creation from an approved `product_candidates` row is not implemented (Phase 8).
- No email/notification on a listing entering the confirm queue.
