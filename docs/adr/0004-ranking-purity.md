# ADR-0004: Ranking purity and versioned weights

- Status: Accepted
- Date: 2026-09-25
- Related: C-06, C-07, C-08, D-10, ADR-0010, [modules/ranking.md](../modules/ranking.md)

## Context

ComparoRank orders organic offers. The public promise (`README.md`, `COMPARORANK.md`) is that
commission, plan, sponsorship and commercial spend never influence it, and that every score is
explainable. The prototype has leaks of commercial properties into consumer orderings (C-06) and a
second, unexplained "Recommended" sort. Weights are edited in the prototype's Ranking Lab.

## Decision

1. **Closed factor set.** `App\Domain\Offers\Ranking\RankingFactor` is an enum with exactly
   `price, trust, delivery, reviews, freshness, availability, shipping`. `RankingWeights::fromArray()`
   rejects unknown factors and negative weights.
2. **No commercial input.** `RankingContext` carries only price, shipping, delivery, merchant rating,
   review count, trust, freshness, availability, coupon validity, completeness, and integrity/compliance
   flags. It has no field for commission, plan, tier, partner status, sponsorship, campaign or spend.
   Architecture tests (`tests/Architecture/RankingPurityTest.php`) fail the build if `RankingContext` gains a
   commercial property, if a commercial factor appears, or if `App\Domain\Offers\Ranking` uses
   `App\Domain\Commercial`, `App\Domain\Affiliate`, `App\Models`, `Illuminate\Database` or facades.
3. **Pure service.** `RankingService::rank(RankingContext, RankingWeights, DateTimeImmutable $evaluatedAt)`
   has no DB, container or clock access.
4. **Versioned weights.** `ranking_versions` (one active row, enforced by the partial unique index
   `ranking_versions_single_active`) and `ranking_weights` (`unique(ranking_version_id, factor)`). The
   initial version `prototype-v1` is inserted by migration `2026_09_25_100650_seed_initial_ranking_version.php`
   with the prototype weights {30, 20, 14, 12, 10, 8, 6}. `RankingVersion::toRankingWeights()` builds
   the value object. Activating a version requires `ranking.configure` and writes an audit entry.
5. **Explanations are versioned.** Every `RankingResult::toArray()` carries `version`, `weights` and
   `evaluated_at`.
6. **Hidden penalties are counted, never itemised.** Outbound-link and integrity (risk) penalties
   affect the score; the explanation exposes only `withheld_checks` (the count).
7. **Promotion is separate.** Promoted rows are inserted after ranking by a separate assembler on
   surfaces with declared slots (C-07). The product offer table has no promoted slots. Offers have no
   `is_sponsored` or `position_boost` column.

## Consequences

- Weight changes are data changes with an audit trail, not deploys; parity fixtures pin `prototype-v1`.
- Cached rank entries must include the ranking version in the key (see target architecture, cache
  catalogue).
- An offer's score depends on the market minimum and shipping median of all offers of the product;
  any offer change of a product invalidates the rank cache of every offer of that product in that market.
- Tie-break is lowest total (as in the prototype), then offer id ascending
  (`App\Domain\Offers\Queries\ProductOfferComparison`).

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| Weights in `config/ranking.php` (BACKEND-MIGRATION.md) | No history, no audit, explanations cannot name a version |
| Open factor map (string keys) | A new factor could be added without review, including a commercial one |
| Itemised hidden penalties | Discloses integrity signals that merchants could game |
