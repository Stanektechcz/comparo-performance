# Analytics

## Event taxonomy

```
search · search_result_click · search_zero_result · product_view · shop_view ·
compare_add · compare_view · deal_view · coupon_reveal · merchant_click ·
affiliate_redirect · review_view · review_submit · follow · save ·
price_alert_create · forum_view · forum_reply · ask_query · sitemap_generate
```

Each event carries entity type, entity id, market, locale, currency, placement, source and an
anonymous session hash — never a raw IP or a persistent fingerprint.

## Funnel

```
organic visit → entity page → offer comparison → merchant click → affiliate conversion
```

Measured per landing page, per market and per source, so a page's value is its contribution to
merchant clicks and commission, not its raw traffic.

## Organic attribution

Sources are distinguished: Google organic, Bing organic, **AI search (cited)**, direct,
community/social and referral, with visits, merchant clicks, conversion rate, revenue and revenue
per visit. AI referrals are separated wherever the referrer or a citation log makes them
detectable; the prototype's numbers are seeded and labelled.

## Page value score

`SEO × 0.4 + GEO × 0.3 + inbound links × 2 + data quality × 0.3`, used to prioritise which pages
get updated first. Real production replaces the proxies with measured sessions, merchant clicks
and attributed commission.

## Growth opportunity score

Search demand + content gap + commercial value + available data − competition proxy, 0–100, per
query. Drives the content-gap list, which outputs opportunity, intent, suggested URL, page type
and priority.

## Content freshness and decay

Tracked timestamps: `created_at`, `updated_at`, `data_updated_at`, `editorial_reviewed_at`.
Decay signals: age, falling clicks, old prices, expired merchants, broken internal links, outdated
data. Actions: update, merge, redirect, noindex, archive. Dates are never touched cosmetically to
fake freshness.

## Experiments

Title, layout and CTA variants per template with simulated CTR, clicks and lift, plus a stated
winner. Production replaces the numbers with a real split at the CDN or rendering layer.

## Merchant-facing analytics

Merchants see product visibility, data completeness, feed quality, offer competitiveness, review
metrics and search appearances — never the internal ranking weights or another merchant's data.

## Migration path from the prototype

| Prototype | Production |
| --- | --- |
| `localStorage` state (`comparo.proto.v2`) | PostgreSQL + Redis, session cookies, RBAC |
| `seed*.js` datasets | migrated schema + feed importers ([DATABASE.md](DATABASE.md)) |
| in-memory page registry | materialised `page` table refreshed by a worker |
| `applyHead()` client meta | server-rendered head, `X-Robots-Tag`, ISR revalidation |
| generated sitemap string | sitemap files on object storage behind the CDN + IndexNow calls |
| seeded search analytics | event pipeline (queue → warehouse) + Search Console / Bing APIs |
| deterministic Ask Comparo | same retrieval over the search index and a vector store, optional LLM phrasing over retrieved facts only |
| manual AI citation log | webmaster APIs and referrer analysis where available |
```
Keep retrieval, ranking and compliance filtering server-side in every step — the phrasing layer
must never be able to invent a fact.
```
