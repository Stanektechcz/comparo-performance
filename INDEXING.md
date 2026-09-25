# Indexing

## States

`index` · `noindex` · `conditional` (decided by data) · `canonicalized` (parameter variant) ·
`expired` (lifecycle rule) · `redirected` (301/302 in the redirect manager).

## Rules per template

| Template | Rule | Condition |
| --- | --- | --- |
| home, category, reviews hub, research, methodology, deal hub | index | always |
| product | conditional | offers ≥ 1 **or** reviews ≥ 3 |
| shop | conditional | verified **or** reviews ≥ 5 |
| brand | conditional | products ≥ 2 |
| ingredient | conditional | products ≥ 2 **and** context copy exists |
| country | conditional | shops delivering ≥ 3 |
| compare | conditional | both products ≥ *offers* threshold and combined reviews ≥ *reviews* threshold |
| guide | conditional | approved and body ≥ 3 paragraphs |
| forum topic | conditional | replies ≥ 2 and UGC quality ≥ 45 |
| public profile | conditional | public and contributions ≥ 3 |
| public list | conditional | public, titled, items ≥ 3 |
| search, account, saved, merchant, admin, seo, /go/ | noindex | always |

Thresholds for the conditional cases are editable in **SEO & Discovery → Indexation** and
recalculate the page registry immediately.

## Facet and parameter policy

| Facet | Policy |
| --- | --- |
| `sort`, `currency`, utm/affiliate params | canonicalized to the clean URL |
| `country` / market | indexable — it changes the offer set, so it gets its own hub |
| `brand + category` | indexable (approved combination with unique data) |
| availability, free shipping, rating | noindex — transient or thin |

## UGC quality score

`body length / 6 + replies × 9 + votes`, capped at 100. Below 45 a forum topic is noindex.
Reviews are never given individual URLs; they are aggregated on product, shop and `/reviews`.

## Deal lifecycle

Expired deals are not left indexed. The configured rule is: keep the page with an expired status
for 14 days (useful for price-history context), then 301 to the product; the deal hub itself stays
canonical. The redirect manager records every one.

## Affiliate safety

`/go/{merchant}/{product}` is `noindex, nofollow`, disallowed in robots.txt and carries no
canonical identity. The canonical destination for a product is always the Comparo entity page.

## Geolocation

Crawlers are never silently redirected into market-specific content. The default canonical
experience is crawlable; a user's delivery-country preference changes presentation only. Market
identity exists solely on explicit `/countries/{country}` URLs.

## Prototype vs production

The prototype computes indexability live from the seeded dataset and applies robots meta in the
document head. Production adds server-rendered meta, an `X-Robots-Tag` fallback, real
`sitemap.xml` files behind a CDN, and IndexNow submission on the events listed in
[ANALYTICS.md](ANALYTICS.md).
