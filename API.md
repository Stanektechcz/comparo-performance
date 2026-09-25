# API

REST/JSON, versioned under `/api/v1`. `Accept-Language` selects locale; `country` and
`currency` are explicit query parameters on every catalogue read, because both change the result.
Cursor pagination (`?cursor=&limit=`). All mutating requests need a CSRF token (cookie sessions)
or a Bearer token (merchant API keys).

## Conventions

- Errors: `{ "error": { "code": "validation_failed", "message": "...", "fields": {...} } }`
  with 400/401/403/404/409/422/429.
- Rate limits (Redis, per IP + per account): search 60/min, auth 5/15 min, reviews 3/day,
  merchant API 600/min, redirects unlimited but bot-filtered. `429` includes `Retry-After`.
- Money is returned as `{ "amount": 39.9, "currency": "EUR" }`; conversion happens server-side.
- Every list response carries `meta.country`, `meta.currency`, `meta.generated_at`.

## Public

```http
GET /api/v1/search?q=wey+isolat&types=product,brand,shop&country=DE&currency=EUR
```
Fuzzy (typo tolerant), synonym-aware, matches name, brand, ingredient, category, SKU and GTIN.
Response groups results by type with `score`, `price_from`, `rating`, `offer_count`.

```http
GET /api/v1/products?category=protein&brand=ironforge&sort=price_asc&country=DE
GET /api/v1/products/{slug}?country=DE&currency=EUR
GET /api/v1/products/{slug}/offers?country=DE&sort=total_asc&filter[free_shipping]=1
GET /api/v1/products/{slug}/price-history?range=90d&country=DE&series=min,avg
GET /api/v1/products/{slug}/reviews?sort=helpful&cursor=
GET /api/v1/compare?ids=1,2,3&country=DE&currency=EUR
GET /api/v1/brands, /brands/{slug}
GET /api/v1/shops?ships_to=DE&sort=rating_desc
GET /api/v1/shops/{slug}, /shops/{slug}/offers, /shops/{slug}/coupons, /shops/{slug}/reviews
GET /api/v1/deals?type=exclusive|coupon|price_drop&country=DE&min_discount=20&ending_within=7d
GET /api/v1/articles, /articles/{slug}
GET /api/v1/countries, /currencies
```

`GET /products/{slug}` returns `compliance`:

```json
{
  "compliance": {
    "status": "prescription_only",
    "country": "DE",
    "reason": "Melatonin above the food supplement threshold is a medicinal product.",
    "source": "National medicines agency",
    "purchasable": false
  },
  "offers": []
}
```

Offer objects always contain the full cost breakdown — the client never computes it:

```json
{
  "merchant": { "slug": "peaksupps", "name": "PeakSupps", "rating": 4.8, "verified": true, "partner": true },
  "price": { "amount": 36.49, "currency": "EUR" },
  "old_price": { "amount": 44.9, "currency": "EUR" },
  "discount_percent": 19,
  "coupon": { "code": "COMPARO15", "title": "15 % off everything", "exclusive": true, "saving": 5.47 },
  "shipping": { "amount": 0, "currency": "EUR", "free_reason": "coupon_free_shipping" },
  "total": { "amount": 31.02, "currency": "EUR" },
  "unit_price": { "amount": 1.03, "currency": "EUR", "unit": "serving" },
  "availability": "in_stock",
  "stock": 172,
  "delivery": { "days_min": 1, "days_max": 2, "warehouse_country": "DE" },
  "sponsored": false,
  "go_url": "/go/peaksupps/whey-isolate-90"
}
```

## Auth and account

```http
POST /api/v1/auth/register        { email, password, nickname, country, consent }
POST /api/v1/auth/login           { email, password, otp? }
POST /api/v1/auth/logout
POST /api/v1/auth/verify-email    { token }
POST /api/v1/auth/password/forgot { email }
POST /api/v1/auth/password/reset  { token, password }
GET  /api/v1/auth/oauth/{google|apple}/redirect · /callback
POST /api/v1/auth/2fa/enable · /verify        (mandatory for merchant + staff roles)

GET   /api/v1/me
PATCH /api/v1/me                   { nickname, country, currency, language, privacy }
GET   /api/v1/me/favorites         POST /me/favorites { product_id }   DELETE /me/favorites/{id}
GET   /api/v1/me/watchlist         POST /me/watchlist { entity_type, entity_id }
GET   /api/v1/me/alerts            POST /me/alerts { product_id, target_total, currency, channels }
DELETE /api/v1/me/alerts/{id}
GET   /api/v1/me/notifications     POST /me/notifications/read
GET   /api/v1/me/reviews
POST  /api/v1/me/export            → 202, emails a JSON archive (GDPR art. 20)
DELETE /api/v1/me                  → erases profile and content (GDPR art. 17)
```

## Reviews

```http
POST /api/v1/reviews               { type, target_id, rating, title, body, pros, cons, sub_ratings, merchant_id?, proof? }
POST /api/v1/reviews/{id}/vote     { value: 1 | -1 }
POST /api/v1/reviews/{id}/report   { reason, note }
POST /api/v1/reviews/{id}/reply    (merchant role, owner of target merchant only)
```

Created reviews are `pending`. Anti-abuse: one review per user per target, 3 reviews/day,
email verification required, IP and device hashes compared against recent submissions, spam
heuristics (caps ratio, punctuation, outbound links, generic praise, burst detection). Order
confirmations are hashed for verification and discarded — no order documents are stored.

## Merchant (Bearer token, scoped to one merchant)

```http
GET  /api/v1/merchant/profile              PATCH /merchant/profile
POST /api/v1/merchant/verification         { legal_name, registration_number, vat, documents[] }
GET  /api/v1/merchant/products             PATCH /merchant/products/{id} { price, stock, availability }
POST /api/v1/merchant/products/bulk        (JSON array, max 5 000 rows)
GET  /api/v1/merchant/feeds                POST /merchant/feeds { url, format, interval }
POST /api/v1/merchant/feeds/{id}/run
GET  /api/v1/merchant/feeds/{id}/items?status=suggested|unmatched
POST /api/v1/merchant/feeds/items/{id}/match { product_id | "create_new" | "skip" }
GET  /api/v1/merchant/coupons              POST /merchant/coupons     DELETE /merchant/coupons/{id}
GET  /api/v1/merchant/analytics?range=30d&group_by=day|product|country|placement
GET  /api/v1/merchant/reviews              POST /merchant/reviews/{id}/reply
GET  /api/v1/merchant/invoices             GET /merchant/subscription
```

## Admin (session + role check + audit log)

```http
GET   /api/v1/admin/dashboard
GET   /api/v1/admin/merchants?status=pending
POST  /api/v1/admin/merchants/{id}/verify | /reject | /suspend   { note }
GET   /api/v1/admin/products               POST /admin/products    PATCH /admin/products/{id}
POST  /api/v1/admin/products/{id}/merge    { into_id }
GET   /api/v1/admin/feed-items?status=unmatched
GET   /api/v1/admin/compliance?country=DE&status=unknown
PUT   /api/v1/admin/compliance             { product_id, country, status, reason, source }
GET   /api/v1/admin/moderation?queue=new|reported|replies|suspicious
POST  /api/v1/admin/reviews/{id}/{approve|reject|hide|flag}      { note }
POST  /api/v1/admin/users/{id}/{ban|request-proof}
GET   /api/v1/admin/offers                 POST /admin/offers/exclusive
GET   /api/v1/admin/sponsored              POST /admin/sponsored
GET   /api/v1/admin/affiliate/{clicks|conversions|commissions}?range=30d
GET   /api/v1/admin/content                POST /admin/content     PATCH /admin/content/{id}
GET   /api/v1/admin/audit-log?actor=&action=&entity=
GET   /api/v1/admin/settings               PUT /admin/settings
```

## Community

```http
GET  /api/v1/community/feed?filter=all|reviews|discussions|deals|questions|guides&cursor=
GET  /api/v1/community/contributors?range=30d
GET  /api/v1/forum/categories
GET  /api/v1/forum/threads?category=&view=latest|trending|unanswered&country=&tag=
POST /api/v1/forum/threads                 { category, kind, title, body, tags[], country?, product_id? }
GET  /api/v1/forum/threads/{slug}
POST /api/v1/forum/threads/{id}/replies    { body, quote_of_id? }
POST /api/v1/forum/threads/{id}/accept     { reply_id }          (author or moderator)
POST /api/v1/forum/{threads|replies}/{id}/vote
GET  /api/v1/guides                        POST /guides           { title, category, body, tags[] }
GET  /api/v1/guides/{slug}                 POST /guides/{id}/vote
GET  /api/v1/community/deals?status=approved&country=
POST /api/v1/community/deals               { merchant_id, product_id, title, price, old_price, code?, country, expires_at, description }
POST /api/v1/community/deals/{id}/vote     { kind: good|expired|wrong }
GET  /api/v1/users/{username}              public profile (404 when hidden)
POST /api/v1/me/follow                     { entity_type, entity_id }    DELETE /me/follow/{type}/{id}
GET  /api/v1/me/lists                      POST /me/lists          { name, visibility }
POST /api/v1/me/mute                       { username }            POST /me/block { username }
POST /api/v1/reports                       { entity_type, entity_id, reason, note? }
GET  /api/v1/shops/{slug}/questions        POST /shops/{slug}/questions { question }
POST /api/v1/merchant/questions/{id}/answer                        (official answer)
GET  /api/v1/merchant/announcements        POST /merchant/announcements { kind, title, body }
GET  /api/v1/countries/{iso}/hub
GET  /api/v1/tags/{tag}                    threads + guides + reviews + deals for one tag
```

Admin adds `/admin/community/{threads|replies|guides|deals|reports}`,
`/admin/reputation` (manual adjustment, audited), `/admin/badges` (define and grant) and
`/admin/flags` (feature flags). Community routes respect feature flags: a disabled surface returns
`404` rather than an empty list.

## Redirect and webhooks

```http
GET  /go/{merchant}/{product}?campaign=&placement=&variant=      → 302
POST /api/v1/webhooks/networks/{network}   (HMAC-signed conversion postback)
POST /api/v1/webhooks/fx                   (daily currency rates)
```

## SEO endpoints

```http
GET /sitemap.xml            index; per-type sitemaps for products, shops, brands, deals, articles
GET /robots.txt             disallows /go/, /search, faceted URLs with more than one active filter
GET /{locale}/products/{slug}    canonical page, hreflang alternates for every enabled locale
```

Structured data emitted per page type: `Organization`, `WebSite` + `SearchAction` (home),
`Product` + `AggregateOffer` + `AggregateRating` + `Review` (product), `Store` (shop),
`BreadcrumbList` (all), `Article` (content), `Offer` (deal). Ratings are emitted only when at
least one approved review exists — no synthetic ratings, ever.
