# ComparoRank

Proprietary organic ranking score, 0–100, that determines the order of offers, merchants and
recommendation surfaces. **Commission, subscription tier, ad spend and sponsorship are not inputs
and cannot change it.** Sponsored placements are labelled and ranked by the same score.

## Inputs and default weights

| Component | Weight | Signal |
|---|---|---|
| Price competitiveness | 30 | total landed price vs the cheapest total for that product in the delivery market |
| Shop trust | 20 | Merchant Trust Score 2.0 (see TRUST-SCORING.md) |
| Fast delivery | 14 | declared delivery window to the selected country |
| Customer rating | 12 | rating, damped by review-count confidence |
| Fresh data | 10 | hours since the offer was last seen in a feed |
| Verified availability | 8 | in stock / low stock / pre-order / out of stock |
| Shipping cost | 6 | shipping vs the market median for that country |
| Offer quality | +7 max | data completeness plus one valid applicable coupon |

Sub-scores are normalised to 0–1, multiplied by their weight, and summed. Weights are re-normalised
to 100 so the Ranking Lab can change them without inflating scores.

## Penalties

| Penalty | Points | Visible to users |
|---|---|---|
| Stale data (> 48 h) | −10 | yes |
| Price under review (anomaly) | −14 | yes |
| Unverified reference price | −8 | yes |
| Market status not verified | −6 | yes |
| Outbound link problem | −10 | no |
| Integrity signals (HIGH / CRITICAL risk) | −6 / −14 | no |

Hidden penalties exist so anti-fraud rules cannot be reverse-engineered. They can only lower a
score, never raise one, and the explanation panel says how many were applied.

## Bands

| Score | Label |
|---|---|
| 90–100 | Exceptional |
| 80–89 | Excellent |
| 70–79 | Good |
| 60–69 | Fair |
| < 60 | Low confidence |

## Best-buy eligibility

An offer is eligible for the **Best value** badge only when it has no price anomaly, is not
compliance-blocked, has a verified market status, and scores ≥ 60. Anomalous offers stay visible
with a "Price under review" badge but are excluded from Best value and from public price aggregates.

## Explainability

Every ranked row carries a **Why this rank?** control that opens the full component breakdown with
points earned, the maximum available per component, all visible penalties and the count of withheld
integrity checks. Prototype implementation: `intel.js → rank(ctx)`.

## Ranking Lab

`/intel → Ranking Lab` re-weights the model live and re-ranks a real product. Changes apply to the
whole prototype while set; "Publish as default" writes an audit entry. Reset returns to the
production defaults in `seed-intel.js → S.ix.rankWeights`.

Future service: **RankingService** (stateless scorer + weight config store + explanation payload).
