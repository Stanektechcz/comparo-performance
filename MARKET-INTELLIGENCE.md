# Market intelligence

## Market coverage

Per market: shops, products, offers, brands, reviews and a normalised coverage score (relative to the
strongest market). Labels: Strong coverage ≥ 80, Adequate ≥ 60, Thin ≥ 40, Weak below. Each thin
market names its gap: few merchants, weak review coverage, or limited catalogue depth.

## Product coverage score

Merchant count × 11 + markets × 2.6 + reviews × 3 + data completeness × 0.25 → Well covered ≥ 75,
Partial ≥ 50, Thin below. Shown publicly on the product page as "Data coverage".

## Category coverage

Products, offers, brands, merchants, search demand and count of missing data fields per category.

## Search-to-supply gap

Compares 30-day search demand against active offer supply per product. Flags:

* **No supply** — demand with zero offers.
* **High demand / thin supply** — > 120 searches with ≤ 2 merchants.
* **Under-supplied** — demand-to-offer ratio above 90.

## Zero-supply alerts

Repeated searches with no purchasable result (creatine gummies, clear whey refresh,
ashwagandha gummies) with market breakdown and a one-click "create canonical product" task.

## Trend dashboard

Fast-growing products, shops, brands, categories and searches — same time-decayed model as the
consumer trending surfaces, so admin and users see one truth.

## Merchant opportunity finder

Products with high search demand, ≤ 3 existing offers, and not listed by that merchant. Shown to the
merchant with the market median price.

## Sales lead engine

`PotentialMerchant`: name, domain, market, category overlap, catalogue size, priority, owner, note.
Pipeline: Identified → Contacted → Interested → Onboarding → Verified → Live, plus Rejected.

## Market configuration

Per market: language, currency, VAT, compliance profile, merchant and affiliate counts, and
per-market feature flags (forum, guides, deal submission, cashback, merchant deals, basket compare,
public profiles) — e.g. cashback off in France, forum off in Sweden, deal submission off in the US.

Future services: **MarketIntelligenceService**, **LeadService**.
