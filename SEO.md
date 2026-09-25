# SEO

Comparo is entity-first: keywords map to entities (product, shop, brand, category, ingredient,
market, comparison), and every entity has one canonical URL, one identity and a scored page.

## URL architecture

```
/                                  home
/products/{slug}                   canonical product entity
/brands/{slug}                     brand entity
/shops/{slug}                      merchant entity
/categories/{slug}                 category hub
/ingredients/{slug}                ingredient knowledge page
/countries/{country}               market hub
/compare/{a}-vs-{b}                comparison (programmatic, quality-gated)
/deals                             deal hub
/reviews                           aggregated review hub
/guides/{slug}                     community guide
/forum, /forum/{category}, /forum/topic/{slug}
/research, /research/{slug}        original datasets
/methodology, /trust               transparency surfaces
/users/{username}                  public contributor profile
/ask, /search, /saved, /account, /merchant, /admin, /seo, /go/...   (all noindex)
```

No query-string SEO pages. Sorting, currency, availability filters and tracking parameters never
create an indexable variant — see [INDEXING.md](INDEXING.md).

## Metadata engine

Templates live in `S.metaTemplates` (one per page type) with `{variable}` placeholders resolved
from live entity data: `{product} {shop} {brand} {category} {ingredient} {country} {offerCount}
{shopCount} {reviewCount} {lowest} {rating}`. Admins edit title and description per template in
**SEO & Discovery → Metadata** with a live SERP preview and length warnings (62 chars title,
165 description). Edits persist and immediately change the emitted `<title>`, description and
Open Graph tags.

## Live head synchronisation

`applyHead()` runs on every route change and writes, for real, into `document.head`:
`title`, `meta[name=description]`, `meta[name=robots]`, `link[rel=canonical]`,
`og:title/description/type`, `twitter:card`, one `link[rel=alternate hreflang]` per locale-market
pair including `x-default`, and a `script[type="application/ld+json"]` with the page's JSON-LD.
Open dev tools on any route and inspect `<head>` — it is not decorative.

## SEO score

0–100 with itemised deductions, computed per page in `scorePage()`:
missing title −18, missing description −12, missing H1 −10, orphan −14, weak links −5,
depth > 3 −6, no structured data −12, thin content −8, stale data −6, non-unique programmatic −10,
over-long title −4. Every deduction is shown in the Page Inspector with its weight — there are no
decorative scores.

## Ranking transparency

Every ranked surface explains itself: the offer table shows a best-value badge with a
"Why this?" modal (total price 50 %, shop rating 25 %, delivery 15 %, verification and stock 10 %),
shop profiles expose the trust score breakdown, and search shows its intent classification and a
"why these results" line. Sponsored placements are labelled, excluded from best-value and cannot
alter ratings or community scores.

## What is prototype-only

Search-console-style impression data, AI citation counts, Core Web Vitals and organic attribution
are seeded or manually imported and labelled as such in the console. Everything structural —
page registry, indexability decisions, metadata rendering, canonical/hreflang/JSON-LD emission,
sitemap generation, scores, redirects, merges — is computed live from the dataset.
