# Price intelligence

## Normalisation

Every offer resolves to: price → best valid coupon → effective price → shipping to the selected
country (free-shipping threshold applied) → **total landed price**, plus unit price, price per serving
and price per 100 g where the pack allows it. All components are displayed separately; comparison
defaults to the total.

## Price confidence (0–100)

Fresh feed < 24 h (−24 if not), merchant verified (−12), historically consistent (−22), valid price
(−18), stock state present (−10), shipping known (−8), link healthy (−6).
Levels: ≥ 88 High, ≥ 70 Moderate, ≥ 50 Low, below that Unreliable.

## Historical statistics

Per product: 7/30/90/365-day averages, historical low and high, median, and 90-day volatility
(range ÷ mean). Computed from the stored daily minimum series.

## Public price badge

| Badge | Rule |
|---|---|
| Exceptional price | within 2 % of the 90-day low |
| Good price | ≥ 6 % below the 90-day average |
| Typical price | within ±6 % of the 90-day average |
| Above average | more than 6 % above the 90-day average |

## Deal timing indicator

Distance to the 90-day low: ≤ 3 % "Strong time to buy", ≤ 10 % "Reasonable", ≤ 22 % "Worth waiting",
otherwise "Poor timing". Phrased as an observation, never as a promise.

## Trend indicator

7/30/90-day moving averages of the cheapest total: downward, upward, likely stable, or highly
volatile (90-day volatility > 26 %). Always labelled "Trend indicator, not a guarantee". No ML.

## Anomaly detection

Zero price, > 55 % below the product median, > 120 % above it. Import validation additionally rejects
negative shipping, impossible discounts and zero prices before publication. Admin actions: approve
as genuine, exclude from ranking, contact merchant. Flagged offers stay visible with a
"Price under review" badge, are excluded from Best value, and never enter public price aggregates.

## Deal Score (0–100)

Discount vs 90-day average, distance to historical low, shop trust, coupon validity, stock, deal
freshness. Labels: ≥ 85 Outstanding, ≥ 72 Strong, ≥ 58 Decent, ≥ 44 Weak, below that Not a deal.

## Coupons

States: verified, merchant verified, community verified, unverified, expired, invalid. Users report
"worked" / "did not work"; a success rate is published only after 12 reports. Total-price
calculations auto-select the best valid applicable coupon and label it "best coupon applied".
Invalid and expired codes are excluded from calculations.

## Stock confidence

Feed age ≤ 6 h High, ≤ 24 h Good, ≤ 48 h Moderate, older Unknown. An in-stock claim older than 48 h
is displayed as "Unknown", not "In stock".

## Basket optimiser

See RECOMMENDATIONS.md → Basket Compare.

Future service: **PricingService** (normalisation on ingest, statistics job, anomaly stream).
