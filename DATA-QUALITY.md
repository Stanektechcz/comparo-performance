# Data quality

## Offer normalisation

price · currency · shipping (per market) · coupon · package size · unit price · availability
(five-state enum) · warehouse country · delivery window. The comparison always shows product
price, shipping, discount and **total**, computed server-side.

## Unit price engine

Price per serving, per 100 g of product and — for protein — per 100 g of protein, so a 900 g tub
and a 4 kg bag are comparable. Variants are never mixed into one price comparison: flavour, pack
size and packaging are separate variant dimensions on the canonical product.

## Offer freshness

| Status | Age of last feed update |
| --- | --- |
| Fresh | < 6 h |
| Aging | 6–24 h |
| Stale | 1–7 days |
| Expired | > 7 days, or availability unknown |

Stale offers are never ranked prominently and never back an Ask Comparo price claim without an age
disclosure. Every offer row shows its update time.

## Data quality score

Per offer and per merchant: EAN present, brand matched, image present, price freshness,
availability freshness, variant confidence, shipping known. Admins can filter low-quality records;
merchants see the same score as a completeness percentage with the specific missing fields.

## Merchant feed health

Last import, items, matched, unmatched, rejected rows, errors, stale offers, missing EAN, missing
images, match rate and landing-page price divergence. Verified status requires match rate > 85 %
and divergence < 3 % — see [MERCHANT-FEEDS.md](MERCHANT-FEEDS.md).

## Merchant recommendations

Generated from the score: add EAN, fix stale offers, update shipping, add images, improve feed
coverage, reply to reviews. Actionable and specific, never a vanity dashboard.

## Anomaly detection

Prototype alerts for price drops beyond 70 %, shipping suddenly zero, abnormal stock counts and
rating-manipulation patterns (bursts of same-rating reviews from new accounts). Anomalies suspend
prominent ranking until reviewed rather than silently publishing.

## Search-engine feed eligibility

Google/Bing merchant-style export architecture is documented, but eligibility is per product and
per market and always subject to the compliance engine: restricted, prescription-only, not-allowed
and unverified products are excluded from external merchant feeds and paid surfaces.
