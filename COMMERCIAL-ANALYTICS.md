# Commercial analytics

## Core metrics

MRR (recurring subscriptions only), ARR (MRR × 12, **excluding** one-off campaign revenue), ARPM,
revenue mix by stream with current / previous / YTD, and revenue by plan, country and merchant.

## MRR bridge

Starting MRR → New → Expansion → Contraction → Churn → Ending MRR, computed from the recorded
subscription changes. Net revenue retention and gross revenue retention are derived from the same
bridge, so the three numbers can never disagree.

## Concentration

Share of commercial revenue held by the top 5 and top 10 merchants — a risk indicator, surfaced on
the Overview when it passes 60 %.

## Cohorts

Merchants grouped by signup month with retained count, retention rate and revenue.

## Attribution

Default model: **last touch, 30-day window**, documented in Settings and applied consistently.
Campaign, affiliate and organic conversions are attributed once under that model — the same
conversion is never counted in two streams.

## Time ranges

7d, 30d, Quarter, YTD, 1y, with previous-period comparison. Export is filter-aware.

## Merchant-facing analytics

Platform share (share of Comparo clicks, active offers, search visibility) — explicitly labelled
**Comparo platform share**, not market share. Search visibility, offer win rate, price
competitiveness against the market median, review analytics and feed analytics. Competitor
comparisons are anonymised and aggregated; no private competitor metric is ever exposed.
