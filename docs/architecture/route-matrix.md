# Route matrix

Ground truth: `php artisan route:list -v --except-vendor` (2026-09-25, 53 routes). Vendor routes
(Fortify auth, Horizon, Telescope, health, dev-tools) are excluded. "Auth" is the middleware stack
after `web`/`api`; "Permission/policy" is the specific gate beyond authentication; "Market context" says
whether `ResolveMarket` applies (all public/catalog routes do via the global `web` group — see
`bootstrap/app.php` — merchant/staff routes do not depend on the delivery market). SEO reflects the
Inertia page's meta defaults, not a per-route override system (none exists yet).

## Public (guest-accessible)

| Method | Path | Name | Surface | Auth | Permission/policy | Market context | SEO | Status |
|---|---|---|---|---|---|---|---|---|
| GET | `/` | `home` | public | none | — | yes | index | FUNCTIONAL |
| GET | `/products` | `products.index` | public | none | — | yes | index | FUNCTIONAL |
| GET | `/products/{slug}` | `products.show` | public | none | compliance gates offer visibility (ADR-0007) | yes | index | FUNCTIONAL |
| GET | `/categories` | `categories.index` | public | none | — | yes | index | FUNCTIONAL |
| GET | `/categories/{category:slug}` | `categories.show` | public | none | — | yes | index | FUNCTIONAL |
| GET | `/brands` | `brands.index` | public | none | — | yes | index | FUNCTIONAL |
| GET | `/brands/{brand:slug}` | `brands.show` | public | none | — | yes | index | FUNCTIONAL |
| GET | `/shops` | `shops.index` | public | none | — | yes | index | FUNCTIONAL |
| GET | `/shops/{slug}` | `shops.show` | public | none | — | yes | index | FUNCTIONAL |
| POST | `/market` | `market.update` | public | none (`throttle:30,1`) | — | writes the session market | n/a | FUNCTIONAL |
| GET | `/.well-known/passkey-endpoints` | `well-known.passkeys` | public | none | — | no | noindex | FUNCTIONAL |
| GET | `/search` | `search` | public | none | `throttle:search-page` (60/min per IP) | yes | noindex,follow | FUNCTIONAL |
| POST | `/search/clicks` | `search.clicks` | public | none | `throttle:search-clicks` (60/min per IP); validated against the recorded search, always 204 | n/a | n/a | FUNCTIONAL |

## Authenticated user (settings)

| Method | Path | Name | Surface | Auth | Permission/policy | Market context | SEO | Status |
|---|---|---|---|---|---|---|---|---|
| ANY | `/settings` | (redirect) | auth | `auth` | — | no | noindex | FUNCTIONAL |
| GET/PATCH/DELETE | `/settings/profile` | `profile.*` | auth | `auth` (+`verified` on delete) | own profile only | no | noindex | FUNCTIONAL |
| GET | `/settings/security` | `security.edit` | auth | `auth`,`verified`,`password.confirm` | own account only | no | noindex | FUNCTIONAL |
| PUT | `/settings/password` | `user-password.update` | auth | `auth`,`verified` (`throttle:6,1`) | own account only | no | noindex | FUNCTIONAL |

## Merchant portal (`/merchant/*`)

Every route: `auth`, `verified`, `feature:merchant-feeds`, `merchant.context` (`ResolveMerchantContext`
— binds the active `MerchantContext`, 403 if the user has no membership); `{feed}`/`{run}`/`{listing}`
resolved through a merchant-scoped query first (foreign id → 404), then policy (see
`docs/security/permission-matrix.md`). No SEO surface (noindex, app-shell pages).

| Method | Path | Name | Policy gate | Notes | Status |
|---|---|---|---|---|---|
| POST | `merchant/context` | `merchant.context.update` | membership only | `throttle:30,1` | FUNCTIONAL |
| GET | `merchant/feeds` | `merchant.feeds.index` | `FeedSourcePolicy::view` (any member) | | FUNCTIONAL |
| GET | `merchant/feeds/create` | `merchant.feeds.create` | `FeedSourcePolicy::create` (owner/manager) | | FUNCTIONAL |
| POST | `merchant/feeds` | `merchant.feeds.store` | `create` | `throttle:20,1` | FUNCTIONAL |
| GET | `merchant/feeds/{feed}` | `merchant.feeds.show` | `view` | | FUNCTIONAL |
| GET | `merchant/feeds/{feed}/edit` | `merchant.feeds.edit` | `update` (owner/manager) | | FUNCTIONAL |
| PUT | `merchant/feeds/{feed}` | `merchant.feeds.update` | `update` | `throttle:30,1` | FUNCTIONAL |
| GET | `merchant/feeds/{feed}/mapping` | `merchant.feeds.mapping.edit` | `update` | | FUNCTIONAL |
| PUT | `merchant/feeds/{feed}/mapping` | `merchant.feeds.mapping.update` | `update` | `throttle:30,1` | FUNCTIONAL |
| GET | `merchant/feeds/{feed}/credentials` | `merchant.feeds.credentials.confirm` | `manageCredentials` (owner only) | + `password.confirm` | FUNCTIONAL |
| PUT | `merchant/feeds/{feed}/credentials` | `merchant.feeds.credentials.update` | `manageCredentials` | + `password.confirm`, `throttle:10,1`; audited `credentials_changed` | FUNCTIONAL |
| POST | `merchant/feeds/{feed}/status` | `merchant.feeds.status.update` | `update` | `throttle:30,1` | FUNCTIONAL |
| POST | `merchant/feeds/{feed}/runs` | `merchant.feeds.runs.store` | `run` (= `update`) | `throttle:10,1` | FUNCTIONAL |
| POST | `merchant/feeds/{feed}/uploads` | `merchant.feeds.uploads.store` | `run` | `throttle:10,1` | FUNCTIONAL |
| GET | `merchant/feeds/{feed}/runs/{run}` | `merchant.feeds.runs.show` | `view` | | FUNCTIONAL |
| POST | `merchant/feeds/{feed}/runs/{run}/cancel` | `merchant.feeds.runs.cancel` | `run` | `throttle:30,1`; no-op past `publishing` | FUNCTIONAL |
| GET | `merchant/feeds/{feed}/runs/{run}/errors.csv` | `merchant.feeds.runs.errors.export` | `view` | `throttle:20,1`; CSV formula-injection neutralised | FUNCTIONAL |
| GET | `merchant/matching` | `merchant.matching.index` | membership only | overview counts | FUNCTIONAL |
| GET | `merchant/matching/suggested` | `merchant.matching.suggested` | membership only | confirm-bucket queue | FUNCTIONAL |
| GET | `merchant/matching/unmatched` | `merchant.matching.unmatched` | membership only | | FUNCTIONAL |
| GET | `merchant/matching/history` | `merchant.matching.history` | membership only | | FUNCTIONAL |
| GET | `merchant/matching/listings/{listing}` | `merchant.matching.listings.show` | `MerchantProductPolicy::view` | | FUNCTIONAL |
| POST | `merchant/matching/listings/{listing}/decision` | `merchant.matching.listings.decision` | `decide` (owner/manager) | `throttle:60,1` | FUNCTIONAL |
| POST | `merchant/matching/listings/{listing}/propose` | `merchant.matching.listings.propose` | `propose` (owner/manager) | `throttle:30,1`; creates/updates `product_candidates` | FUNCTIONAL |
| GET | `merchant/catalogue/products/search` | `merchant.catalogue.products.search` | membership only | `throttle:60,1`; used by the proposal/link UI | FUNCTIONAL |

## Staff console (`/admin/catalogue/matching/*`)

Every route: `auth`, `verified`, `staff.access`, `matching.review`; the relink route additionally
requires `offers.manage`. Staff prefix is `/admin` (not `/staff`) per the open-decision safe default
(A-05); Horizon stays at `/staff/horizon`.

| Method | Path | Name | Extra permission | Notes | Status |
|---|---|---|---|---|---|
| GET | `admin/catalogue/matching` | `admin.catalogue.matching.index` | — | staff review queue | FUNCTIONAL |
| GET | `admin/catalogue/matching/listings/{listing}` | `admin.catalogue.matching.listings.show` | — | | FUNCTIONAL |
| POST | `admin/catalogue/matching/listings/{listing}/decision` | `admin.catalogue.matching.listings.decision` | — | `throttle:60,1` | FUNCTIONAL |
| POST | `admin/catalogue/matching/listings/{listing}/rematch` | `admin.catalogue.matching.listings.rematch` | `offers.manage` | `throttle:60,1`; relinks a published listing | FUNCTIONAL |
| POST | `admin/catalogue/matching/candidates/{candidate}/resolve` | `admin.catalogue.matching.candidates.resolve` | — | `throttle:60,1` | FUNCTIONAL |
| POST | `admin/catalogue/matching/conflicts/{conflict}/resolve` | `admin.catalogue.matching.conflicts.resolve` | — | `throttle:60,1` | FUNCTIONAL |
| GET | `admin/catalogue/products/search` | `admin.catalogue.products.search` | — | `throttle:60,1` | FUNCTIONAL |

## API

| Method | Path | Name | Surface | Auth | Notes | Status |
|---|---|---|---|---|---|---|
| GET | `api/public/v1/products/{slug}/offers` | `api.public.v1.products.offers` | public API | none | `throttle:public-api` named limiter | FUNCTIONAL |
| GET | `api/public/v1/search/suggest` | `api.public.v1.search.suggest` | public API | none | `throttle:search-suggest` (120/min per IP); 2–64 char query, 60 s cache per market + normalised prefix | FUNCTIONAL |
| GET | `api/merchant/v1/offers` | `api.merchant.v1.offers.index` | merchant API | `auth:sanctum` | `throttle:60,1` | FUNCTIONAL |
| GET | `api/merchant/v1/offers/{offer}` | `api.merchant.v1.offers.show` | merchant API | `auth:sanctum` | `throttle:60,1` | FUNCTIONAL |
| GET | `api/user` | `api.user` | internal | `auth:sanctum` | framework default | FUNCTIONAL |

## Known gaps

- No route yet enforces `URL_DOMAIN_MISMATCH` at click time (`/go` redirect is Phase 5); it is a
  feed-import warning only today.
- No per-route SEO override table exists; index/noindex above reflects the page's own defaults, not a
  configurable field.
