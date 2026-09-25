# Fraud and abuse detection

All fraud output is internal. The platform never publicly accuses a user or merchant; it hides or
holds content and routes it to moderation with an explanation.

## Review Trust Confidence (0–100)

Penalties: duplicate text −34, arrived inside a burst −24, shared device fingerprint −16,
very young account −14, no verified purchase −10, repeated target −8, very short body −6.

Levels: ≥ 85 High confidence, ≥ 65 Normal, ≥ 45 Needs review, below 45 Suspicious. The moderation
queue is ordered by ascending confidence, so suspicious content surfaces first.

## Duplicate detection

Similarity = 0.5 × token Jaccard + 0.5 × character-trigram Dice. Clusters show the seed review, each
related review with its similarity percentage, the author, rating and timestamp. Actions: keep,
hide, hide whole cluster, merge into one integrity report, investigate.

## Burst detection

Per-merchant hourly histogram over 72 h against a 30-day baseline. Flagged when peak ≥ 6 in one hour
**and** peak ≥ 8× the hourly baseline. Example seeded case: baseline 3 reviews/day, 22 reviews in 3 h.

## Rating manipulation

90-day running average with 7-day deltas. |Δ| ≥ 0.45 marks a spike; the direction classifies it as a
positive manipulation pattern or a negative campaign. Seeded cases: positive manipulation on
SupplementBay, review bombing on BodyCore Market.

## Fake discount protection

An offer is flagged when its reference ("was") price sits ≥ 25 % above the 12-month median. The
public UI then withholds the discount percentage and shows "Reference price unverified" until the
price is verified; ComparoRank applies a −8 penalty.

## Price anomalies

Zero price, more than 55 % below the product median, more than 120 % above it, plus import-time
rejections (negative shipping, impossible discount, zero price). See PRICE-INTELLIGENCE.md.

## Community abuse controls

5 posts / 10 min, 3 reviews / day, ≥ 85 % similarity blocked, max 2 links per post for accounts
under 30 days, no deal submission in the first 48 h, 3 upheld reports → review queue.

Future service: **FraudService** (async scoring on write + nightly re-scan).
