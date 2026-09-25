# Product matching engine 2.0

Maps merchant feed rows to canonical products and produces an explainable confidence score.

## Signals

| Signal | Points |
|---|---|
| EAN / GTIN exact | +50 |
| Brand exact (incl. resolved alias) | +15 |
| Brand found in title | +9 |
| Title similarity (0–22, scaled) | up to +22 |
| Package size match | +10 |
| Known alternate pack | +6 |
| Variant match | +7 |
| Ingredient-set overlap | +5 |
| Package size differs | −12 |

## Confidence bands

| Score | Level | Handling |
|---|---|---|
| 100 | Exact | auto-match |
| 90–99 | Very high | auto-match |
| 80–89 | High | needs confirmation |
| 65–79 | Possible | needs confirmation |
| < 65 | Manual review | unmatched queue |

Every row exposes its component breakdown ("EAN exact match +50, Brand exact +15, Title similarity
94 % +18, Package size match +10").

## Variant intelligence

A merge is blocked when pack size, serving count, brand, formulation or flavour set differ. The guard
runs on both duplicate resolution and feed matching, so a 2 kg pack never folds into a 4 kg pack and
a powder never folds into capsules.

## Clustering

Feed rows above 65 confidence group under their canonical product, showing every merchant listing,
its raw title, price and score — the visible form of the canonical product graph.

## Brand resolution

Alternate spellings (PeakLabs / Peak-Labs / PEAK LABS) resolve to one canonical brand. Aliases are
suggested from feed evidence and approved by the catalogue team.

## New product discovery

When unmatched rows repeat across ≥ 2 independent feeds, or a zero-result query has repeated demand,
a candidate canonical product is proposed with its evidence and can be approved in one action.

## Field conflicts and source priority

Conflicting EAN, brand, pack or variant values are queued with each value, its source and how many
sources report it. Resolution follows the source hierarchy: admin verified (100) → official brand
data (88) → verified merchant feed (70) → other merchant feed (48) → community (30).

Compliance status is **never** inferred automatically.

## Surfaces

* Merchant: `/merchant → Match centre` (buckets, bulk actions, per-row explanation, import history, diff).
* Admin: `/intel → Match centre` (all feeds, candidates, clusters, brand aliases, field conflicts, lineage).

Future service: **MatchingService** (queue worker per feed import + review API).
