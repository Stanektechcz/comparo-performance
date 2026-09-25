# Recommendations, related graph and basket optimisation

Nothing here is random. Every surface is a deterministic score with a stated reason.

## Related product graph

Same category +30, same brand +22, shared ingredient +14 each, similar price band (±20 %) +14,
frequently compared (> 40 comparisons/30 d) +8, same unit +4. Threshold 30. Products carry
"Why am I seeing this?" which lists the exact reasons.

## Shop recommendation / similar shops

Shared delivery markets ×4, shared categories ×7, similar trust level +8, similar rating +6.
Shown on every shop profile with its reason string.

## Trending

Time-decayed composite: searches ×0.4, views ×0.2, saves ×2.4, comparisons ×2.1, merchant clicks
×0.02, 7-day delta ×6. Saves and comparisons are per-account capped in the event model, so a single
user cannot move a ranking. Separate boards for products, shops, brands, categories and searches.

## Basket Compare

`/basket`. Add products, choose quantities, and the optimiser returns four answers:

1. **Cheapest single shop** — only shops that stock every line and deliver to the country.
2. **Lowest split order** — cheapest line-by-line, shipping charged once per shop.
3. **Lowest shipping** — single delivery with the cheapest carrier cost.
4. **Highest trust** — best trust score among complete single-shop options.

Each line is priced with its best valid coupon; free-shipping thresholds apply to the shop subtotal.
Split orders are only recommended when the total still wins after per-shop shipping.

### Free shipping threshold engine

"Add €8.20 at PeakSupps to cross the €85 free-shipping threshold and save €3.40" — computed from the
live subtotal, never a generic nudge.

## Recommendation experiments

Price-first, trust-first and balanced (ComparoRank) strategies run as a live experiment. The organic
default stays balanced and transparent regardless of the experiment result.

Future service: **RecommendationService** (precomputed relation table + online re-ranking).
