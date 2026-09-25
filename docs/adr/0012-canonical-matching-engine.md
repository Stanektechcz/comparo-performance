# ADR-0012: Canonical matching engine

- Status: Accepted
- Date: 2026-09-25
- Related: C-23, D-25, `docs/architecture/phase-2-feeds-matching.md` §1, §5, §9; ADR-0002, ADR-0010,
  [modules/matching.md](../modules/matching.md)

## Context

The prototype scores candidate matches in two places that disagree: the HTML page's inline `matchItem`
function (0.98/0.95 similarity bands) and `intel.js`'s "Product Matching Engine 2.0" (weighted signals,
90/65 buckets). C-23 requires one canonical engine. The engine must be pure (no DB, no clock) so it can
run identically inside a queued job and inside the parity harness (ADR-0010), and its weights must be
changeable without a deploy.

## Decision

1. **`App\Domain\Matching\Engine\ProductMatcher`** is the single scoring engine, ported from intel.js
   Engine 2.0 with exact parity against `tests/Fixtures/PrototypeParity/matching.json` (candidate,
   synthetic, similarity and anomaly fixtures). It is pure: `MatchingPolicy` and DTOs
   (`FeedItemFacts`, `CandidateProduct`, `BrandAliasSet`) in, `MatchResult` (score, `MatchLevel`,
   `MatchPart[]` with `MatchPartLabel`, `MatchSignal[]`) out. `App\Domain\Shared\Text\TextFold` /
   `TitleSimilarity` provide the pure folding/Jaccard-style similarity signal.
2. **Weights and thresholds are versioned**, not hard-coded: `MatchingPolicy::prototypeV1()` defines
   `PROTOTYPE_WEIGHTS` (`ean_exact` 50, `brand_exact` 15, `brand_in_title` 9,
   `title_similarity_scale` 22, `pack_exact` 10, `pack_alternate` 6, `pack_differs` −12, `variant` 7,
   `ingredient` 5), `PROTOTYPE_THRESHOLDS` (`auto` 90, `review` 65) and `PROTOTYPE_LEVELS` (`exact`
   100, `very_high` 90, `high` 80, `possible` 65). The `matching_policies` table stores this as the
   single active row (partial unique index, seeded by migration `2026_09_25_100810`), read through
   `Matching\Queries\ActiveMatchingPolicy`/`ActivePolicy`; `MatchingPolicy::fromArray()` rejects
   malformed or unknown keys rather than matching silently.
3. **Buckets** (`MatchBucket`): `auto` (score ≥ 90) publishes automatically when
   `matching-auto-publish` is on; `confirm` (65–89) waits in the merchant/staff review queue; below 65
   is `unmatched` and only creates a `product_candidates` proposal on explicit merchant action.
4. **Candidate narrowing (deviation #3).** The prototype scores every product in the catalogue and
   falls back to product #1 at score 0 when nothing matches. `Matching\Queries\CandidateProducts`
   instead narrows to EAN-exact ∪ brand-or-alias ∪ brand-in-title matches, ordered by product id, before
   scoring. Without any of those signals the maximum reachable score is
   `brand_in_title(9) + title_similarity_scale(22) + pack_exact(10) + variant(7) + ingredient(5) = 53`
   in the worst case, and in practice ≤ 44 once title similarity and pack/variant/ingredient signals are
   combined honestly — both below the 65 review threshold — so narrowing changes no product's bucket.
   Parity is proven on the full seeded catalogue, not just the fixtures.
5. **Every decision is recorded**, never just toasted: `matching_decisions` is append-only (Eloquent
   guard + DB triggers, `App\Domain\Platform\Exceptions\AppendOnlyViolation`), one row per decision,
   linked into a **linear chain** by `supersedes_id` (unique). `MatchDecisionKind`: `auto`, `suggested`,
   `manual`, `rejected`, `rematch`, `unlinked`. `decided_by_user_id` has no foreign key (user deletion
   must stay possible; audit history is pseudonymous — see ADR-0014 R3). `Matching\Actions\MatchListing`,
   `DecideMatch`, `Rematch`, `ProposeProductCandidate`, `ResolveProductCandidate`, `ResolveConflict`
   are the only writers.
6. **Compliance hold is blocked-in-market only (A-19).** A match against a product that is
   `not_allowed` or `prescription_only` in the feed's market is written (`ComplianceHold` bucket /
   `ListingMatchStatus::ComplianceHold`) but never published as an offer; `unknown` (unreviewed) does
   not hold the match, consistent with ADR-0007's ranking-still-scores/serialization-still-blocks split.
   `Matching\Actions\ComplianceHolds` and `Matching\Contracts\ComplianceHoldCheck` isolate this check so
   `Matching` depends only on `Compliance`'s read contract, never the reverse.
7. **No auto-created canonical products (A-15).** A score below the review threshold, or a rejected
   `confirm` suggestion, produces a `product_candidates` proposal (`CandidateStatus::Proposed`) for
   staff review (`Admin\Catalogue\CandidateResolutionController`); creating the canonical product row
   stays a manual staff catalogue action, deferred in full to Phase 8.
8. **Staff conflict queue** (`matching_conflicts` + `matching_conflict_values`, `ConflictKind`:
   `field_conflict`, `compliance_hold`, `merge_blocked`; `ConflictStatus`: `open`, `resolved`,
   `dismissed`) surfaces disagreements the pipeline cannot resolve automatically, gated by
   `matching.review` (+ `offers.manage` to relink a published listing).

## Consequences

- One engine, one set of weights, one parity fixture set — no more HTML/`intel.js` disagreement.
- Changing weights is a data migration to `matching_policies`, not a code change to `ProductMatcher`.
- Deviation #3 (candidate narrowing) is a documented, provable behaviour change from the prototype; it
  must stay covered by a full-catalogue parity test, not only the curated fixtures.
- The append-only chain makes every current `match_status`/`match_score` on `merchant_products`
  reconstructable from `matching_decisions`, at the cost of one row per re-match instead of an update.

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| Port the HTML `matchItem` bands instead | Discards the richer signal set intel.js already carries; C-23 asks for one engine, and Engine 2.0 is the more complete of the two |
| Score the full catalogue every time (prototype behaviour) | O(products) per feed row does not scale past the demo catalogue; deviation #3's bound shows it changes no outcome |
| Auto-create canonical products from `auto`-bucket candidates that aren't yet product rows | Removes the one human checkpoint before a product enters search/ranking; deferred to Phase 8 as a deliberate scope cut (A-15) |
