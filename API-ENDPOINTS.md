# API endpoints — Laravel implementation map

Every endpoint below corresponds to a function that already exists in the prototype. The column
**Prototype source** names it, so the backend developer can read the exact logic being replaced
rather than inferring it from a description.

Base: `/api/v1`. All responses JSON:API-ish (`data`, `meta`, `links`). Auth: Sanctum for the SPA,
personal access tokens for the public read API.

**Every price endpoint requires a delivery country.** A price without one is not a value this
platform publishes — return `422` rather than defaulting silently.

---

## Conventions

```
GET    /api/v1/{resource}              index      ?country=DE&currency=EUR&page=1&per_page=24
GET    /api/v1/{resource}/{slug}       show
POST   /api/v1/{resource}              store      201 + Location
PATCH  /api/v1/{resource}/{id}         update
DELETE /api/v1/{resource}/{id}         destroy    204
```

Standard query parameters on every index: `country` (ISO-3166-1 alpha-2, **required** on priced
resources), `currency` (ISO-4217), `q`, `sort`, `page`, `per_page`, `include`.

Errors: RFC 7807 problem+json. Rate limits: `X-RateLimit-*` headers, per API plan.

---

## 1. Catalogue (public, cacheable)

| Method | Endpoint | Prototype source | Notes |
|---|---|---|---|
| GET | `/products` | `buildProductIndex()` | filters: `category`, `brand`, `ingredient`, `min_price`, `max_price`, `in_stock`, `has_deal` |
| GET | `/products/{slug}` | `buildProduct()` | includes offers, price stats, compliance state for `country` |
| GET | `/products/{slug}/offers` | `publicRows()` + `offerRow()` | **the core endpoint** — total landed price per merchant |
| GET | `/products/{slug}/price-history` | `chart()` | `?range=7,30,90,365,max&merchant_id=` |
| GET | `/products/{slug}/reviews` | `ratingOf('product')` | credibility-weighted average in `meta` |
| GET | `/products/{slug}/similar` | `ix.related()` | deterministic relationship score |
| GET | `/categories` | `buildCategory()` (index branch) | |
| GET | `/categories/{slug}` | `buildCategory()` | |
| GET | `/brands` / `/brands/{slug}` | `buildBrands()` / `buildBrand()` | brand-spelling resolution applies |
| GET | `/ingredients` / `/ingredients/{slug}` | `buildIngredients()` | |
| GET | `/markets` / `/markets/{iso}` | `buildCountry()` | coverage, compliance, currency |

### `GET /products/{slug}/offers` — response shape

```jsonc
{
  "data": [{
    "merchant": { "id": "SHP-1", "name": "PeakSupps", "slug": "peaksupps",
                  "trust_score": 95, "verified": true },
    "price":        { "amount": 4390, "currency": "EUR" },   // minor units, always
    "shipping":     { "amount": 390,  "free_over": 5000 },
    "coupon":       { "code": "PEAK10", "saving": 439, "auto_applied": true,
                      "success_rate": 0.94, "sample": 112 },
    "total":        { "amount": 4341, "currency": "EUR" },   // what we rank on
    "availability": "in_stock",
    "stock_confidence": 0.92,
    "delivery":     { "min_days": 1, "max_days": 2, "from": "DE" },
    "comparo_rank": { "score": 91, "label": "Exceptional",
                      "breakdown": [ { "factor": "Price competitiveness", "points": 24 } ] },
    "flags":        { "anomaly": false, "reference_price_unverified": false },
    "sponsored":    false,
    "affiliate_url": "/go/peaksupps/PRD-6?subid=..."
  }],
  "meta": { "country": "DE", "currency": "EUR", "market_min": 4341, "market_median": 4790,
            "excluded": { "does_not_ship": 3, "compliance_blocked": 0, "price_anomaly": 1 } }
}
```

`excluded` is not decoration — it is why the list is shorter than the merchant count, and the UI
renders it verbatim in the empty state.

---

## 2. Comparison

| Method | Endpoint | Prototype source |
|---|---|---|
| GET | `/compare/products?ids=` | `buildCompare()` |
| GET | `/compare/shops?ids=` | `buildShopCompare()` — includes `overlap[]`, the shared catalogue |
| POST | `/basket/optimise` | `buildBasket()` — body `{items:[{product_id,qty}],country}` |

`POST /basket/optimise` returns the four strategies the prototype computes: `single_shop`,
`split_order`, `lowest_shipping`, `highest_trust`, each with line items, totals and the delta
against the cheapest.

---

## 3. Deals and coupons

| Method | Endpoint | Prototype source |
|---|---|---|
| GET | `/deals` | `dealItems()` — `?type=drop,coupon,exclusive&ending_soon=1` |
| GET | `/coupons/{code}` | `allCoupons()` + `ix.couponMeta()` |
| POST | `/coupons/{code}/report` | `reportCoupon()` — body `{worked:bool}` → recomputes success rate |

---

## 4. Search

| Method | Endpoint | Prototype source |
|---|---|---|
| GET | `/search?q=` | `searchAll()` — products, brands, shops, ingredients, content |
| GET | `/search/suggest?q=` | `suggestions()` — typeahead, ≤8 results, ≤100 ms |
| GET | `/search/intent?q=` | `ix.searchIntent()` — classifies transactional / informational / navigational |
| POST | `/search/log` | `logSearch()` — feeds zero-result analytics and the content engine |

Implementation: Meilisearch or Typesense, **not** SQL `LIKE`. Index products, brands, shops,
ingredients and published content. Synonyms and typo tolerance matter here — `kreatin`, `creatin`
and `creatine` are the same intent, as are `whey isolat` and `whey isolate`.

---

## 5. Reviews and verification

| Method | Endpoint | Prototype source |
|---|---|---|
| GET | `/reviews` | `buildReviewsHub()` — `?type=product,shop&sort=` |
| POST | `/reviews` | review modal submit |
| GET | `/shops/{slug}/reviews` | `ratingOf('merchant')` |
| POST | `/reviews/{id}/helpful` | `voteHelpful()` |
| POST | `/reviews/{id}/report` | `reportThing()` |
| POST | `/reviews/{id}/reply` | merchant reply — requires `reviews.reply` |

### Purchase verification — three routes

| Method | Endpoint | Prototype source | Notes |
|---|---|---|---|
| POST | `/verification/click-match` | `purchaseProofRoutes()` route 1 | **auto-verifies**: checks our own redirect log for this user + merchant within 45 days |
| POST | `/verification/email` | route 2 | returns the personal forwarding address; an inbound-mail webhook parses sender domain, order date and total |
| POST | `/verification/receipt` | route 3 | multipart upload → moderation queue |
| GET | `/admin/verification/pending` | `pendingProofs()` | moderator queue with the named checks |
| POST | `/admin/verification/{key}/decide` | `resolveProof()` | body `{verdict:"verified"\|"rejected"}` |

The click-match route is the one worth building carefully: it is first-party evidence no competitor
holds, and it needs no user action. Implement as a query against the `affiliate_clicks` table.

Inbound email: Mailgun/Postmark routes → `POST /webhooks/inbound-mail`. Parse, verify against
`offer_price_history` for that merchant and week, then discard the message body. **Never store the
raw email.**

---

## 6. Community

| Method | Endpoint | Prototype source |
|---|---|---|
| GET | `/threads` / `/threads/{slug}` | `buildForum()` / `buildThread()` |
| POST | `/threads` | new-topic modal |
| POST | `/threads/{id}/replies` | `addReply()` |
| POST | `/threads/{id}/vote` | `voteThread()` |
| GET | `/guides` / `/guides/{slug}` | `buildGuides()` |
| POST | `/guides` | guide modal |
| GET | `/community/feed` | `buildCommunity()` |
| GET | `/users/{handle}` | `buildUserProfile()` |
| POST | `/users/{handle}/follow` | `toggleFollow()` |

---

## 7. Account (auth required)

| Method | Endpoint | Prototype source |
|---|---|---|
| GET | `/me` | `state.session` |
| PATCH | `/me` | account settings |
| GET/POST/DELETE | `/me/saved` | `toggleSaved()` |
| GET/POST/DELETE | `/me/alerts` | `addAlert()` — body `{product_id,target_price,type}` |
| GET/POST/DELETE | `/me/follows` | `toggleFollow()` |
| GET | `/me/for-you` | `buildForYou()` — personalised feed with `why[]` per item |
| GET | `/me/notifications` | `notifications()` |
| POST | `/me/notifications/read` | |
| GET | `/me/export` | GDPR export — reviews, posts, lists, follows, alerts, preferences |
| DELETE | `/me` | GDPR erase — anonymises retained community content |

Every `/me/for-you` item carries `why` (`"Because you follow PeakSupps"`), because an unexplained
recommendation is indistinguishable from an ad.

---

## 8. Merchant (auth + `merchant` role, scoped to own shop)

| Method | Endpoint | Prototype source |
|---|---|---|
| GET | `/merchant/dashboard` | `buildMerchant()` |
| GET | `/merchant/offers` | own offers only |
| PATCH | `/merchant/offers/{id}` | |
| GET | `/merchant/feed` | `feedHealth()` |
| POST | `/merchant/feed/import` | queues `ImportMerchantFeed` |
| GET | `/merchant/feed/imports` | version history + diff |
| GET | `/merchant/matching` | `ix.matchCandidates()` — Feed Match Center |
| POST | `/merchant/matching/{id}/confirm` | |
| POST | `/merchant/matching/{id}/reject` | |
| GET | `/merchant/analytics` | clicks, CVR, EPC, offer freshness |
| GET | `/merchant/competitiveness` | `ix.benchmark()` — **anonymised** category medians |
| GET/POST | `/merchant/deals` | |
| GET | `/merchant/reviews` | own reviews + reply |
| GET | `/merchant/plan` | entitlements + usage |
| GET | `/merchant/invoices` | own invoices only |
| GET | `/merchant/api-keys` | |
| POST | `/merchant/api-keys` | returns the token **once** |
| DELETE | `/merchant/api-keys/{id}` | revoke |

**Authorisation is the hard requirement here.** Every merchant endpoint must scope by
`auth()->user()->merchant_id` in a policy, not in a controller `if`. A merchant must never be able
to read another merchant's analytics, billing or risk data — enforce with a global scope on the
model plus a policy test per endpoint.

---

## 9. Staff consoles (auth + staff role + granular permission)

| Area | Endpoints | Permission |
|---|---|---|
| Moderation | `GET/POST /admin/moderation/{queue}` | `review.moderate` |
| Merchants | `GET /admin/merchants`, `POST /admin/merchants/{id}/approve\|suspend` | `merchant.approve`, `merchant.suspend` |
| Compliance | `GET/PATCH /admin/compliance` | `compliance.edit` |
| Risk | `GET /admin/risk`, `POST /admin/risk/{id}/action` | `risk.view` |
| Matching | `GET /admin/matching`, `POST /admin/products/{id}/merge` | `catalog.merge` |
| Pricing | `GET /admin/anomalies`, `POST /admin/anomalies/{id}/decide` | `pricing.review` |
| SEO | `GET /admin/seo/*` | `seo.manage` |
| Growth | `GET /admin/growth/*` | `growth.view` |
| Commercial | `GET /admin/commercial/*` | `commercial.view` |
| Invoices | `POST /admin/invoices/{id}/mark-paid\|void\|credit` | `invoice.edit`, `invoice.void`, `credit.create` |
| Automations | `GET/POST /admin/automations` | `automation.manage` |
| Audit | `GET /admin/audit`, `GET /admin/audit/export` | `audit.view` |

Use `spatie/laravel-permission`. The prototype's role list maps 1:1: Super Admin, Marketplace Admin,
Merchant Manager, Compliance Manager, SEO Manager, Community Moderator, Affiliate Manager, Analyst,
Support, plus the commercial roles (Commercial Admin, Sales Manager, Account Manager, Finance,
Campaign Manager).

---

## 10. Affiliate redirect (the revenue path)

```
GET /go/{merchant}/{product}?subid=&campaign=
```

Prototype source: `logClick()`. Must:

1. Record the click (user, session, merchant, product, placement, country, device, referrer, subid).
2. Resolve the destination from the merchant's tracking template.
3. Append attribution parameters.
4. `302` — never render an interstitial that delays the user.
5. Be excluded from robots and carry `rel="sponsored nofollow"` on the source link.

Target: **< 50 ms**. Write the click to a queue (Redis → batch insert), not synchronously.

`POST /webhooks/affiliate/{network}` receives conversion postbacks (Awin, Tradedoubler, Impact,
direct). Reconciliation compares tracked clicks against network-reported conversions —
`GET /admin/affiliate/reconciliation`, prototype source `cm.reconciliation()`.

---

## 11. Public read API (monetised — see API-MONETIZATION.md)

| Endpoint | Plan | Rate limit |
|---|---|---|
| `GET /public/v1/products` | Developer+ | 60/min |
| `GET /public/v1/products/{id}/offers` | Developer+ | 60/min |
| `GET /public/v1/products/{id}/price-history` | Business+ | 120/min |
| `GET /public/v1/merchants` | Developer+ | 60/min |
| `GET /public/v1/merchants/{id}/trust` | Business+ | 120/min |
| `GET /public/v1/deals` | Developer+ | 60/min |
| `GET /public/v1/reviews/aggregate` | Business+ | 120/min |
| `GET /public/v1/market-intelligence` | Enterprise | 300/min |

Never exposed publicly: internal risk scores, fraud signals, merchant commercial terms, individual
user behaviour, personal data. Enforce with an API resource layer, not by remembering.

Webhooks (Business+): `price.updated`, `offer.created`, `deal.created`, `merchant.rating_changed`.
HMAC-signed, retried with exponential backoff, delivery log retained 30 days.

---

## 12. Machine-readable surfaces

| Endpoint | Generated from |
|---|---|
| `GET /robots.txt` | indexation rules — cached, regenerated on rule change |
| `GET /llms.txt` | static, reviewed per release |
| `GET /sitemap.xml` | sitemap index |
| `GET /sitemap-{products,shops,brands,categories,ingredients,markets,content}.xml` | `php artisan sitemap:generate`, nightly, chunked at 50k URLs |
| `GET /feeds/deals.xml` | RSS of current deals |

JSON-LD is rendered server-side into each page: `Product` + `Offer` + `AggregateRating` on product
pages, `Organization` on shop pages, `BreadcrumbList` everywhere, `FAQPage` on FAQ content,
`Article` + `Dataset` on research. Prototype source: `jsonLd()`.


## Orders

| Method | Path | Prototype source |
|---|---|---|
| GET | `/me/orders` | `allOrders()` filtered by `sessionUserId()` |
| GET | `/orders/{id}` | `orderRow()` |
| GET | `/shops/{slug}/delivery?market=DE` | `deliveryStats(merchantId, iso)` — 204 below the 8-order minimum sample |
| POST | `/orders/{id}/review` | order-backed review; verification is implied by the order, not asked for |
| POST | `/orders/{id}/return` | sets `returned`, `returnReason`, `refund` |
| POST | `/orders/{id}/dispute` | sets `disputed`, `disputeReason` |

## Dosing

| Method | Path | Prototype source |
|---|---|---|
| GET | `/products/{slug}/doses` | `doseRows(p)` — amounts, carrier flag, NRV share, share of scoop |
| GET | `/products/{slug}/unit-price` | `costPerActiveG(p)` |
| GET | `/ingredients/{slug}/cheapest` | `cheapestSourceOf(name)` — nightly precompute in production |
| GET | `/products/{slug}/limits?market=DE` | `doseLimits(p, iso)` |

## Public commercial pages

| Method | Path | Prototype source |
|---|---|---|
| GET | `/for-merchants/pricing` | `buildPublicCommercial('pricing')` — `cx.plans`, `cm.planMatrix()`, live plan counts |
| GET | `/developers` | `buildPublicCommercial('developers')` — `cx.apiPlans`, `cx.dataProducts` |
| GET | `/advertising` | `buildPublicCommercial('advertising')` — `cx.placements` with live booked/capacity |

## Data confidence

| Method | Path | Prototype source |
|---|---|---|
| POST | `/offers/{id}/report` | `reportOffer()` — response carries the confidence movement |
| GET | `/offers/{id}/confidence` | `confidenceOf(o)` — feed signals plus open shopper reports |
