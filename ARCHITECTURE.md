# Architecture

## Shape

API-first, modular, service-based. The web app never talks to the database directly; every read
goes through the API or the search index, every write through a domain service that owns its
invariants.

```
                    ┌──────────────┐
   Browser  ───────▶│  Next.js web │  SSR/ISR pages, hreflang routing, structured data
                    └──────┬───────┘
                           │ REST/JSON (+ signed internal token)
                    ┌──────▼───────┐        ┌───────────────┐
                    │   API layer  │───────▶│ Meilisearch   │  products, brands, shops, articles
                    │  (modules)   │        └───────────────┘
                    └──┬────────┬──┘
                       │        │           ┌───────────────┐
        ┌──────────────▼─┐   ┌──▼─────────┐ │ Redis         │ cache, queues, rate limits
        │ PostgreSQL     │   │ S3 storage │ └───────────────┘
        └────────────────┘   └────────────┘
                       ▲
                       │  queue workers
        ┌──────────────┴──────────────────────────────────┐
        │ feed-import · matching · price-snapshot ·       │
        │ alert-dispatch · affiliate-ingest · sitemap     │
        └─────────────────────────────────────────────────┘
```

## Modules (bounded contexts)

| Module | Owns | Key rule |
| --- | --- | --- |
| `catalog` | Brand, Category, Product, ProductVariant, Ingredient | canonical product identity; merchants never write here directly |
| `merchant` | Merchant, MerchantUser, MerchantVerification, Subscription, Invoice | verification state machine |
| `feeds` | MerchantProduct, feed runs, matching decisions | one merchant SKU maps to at most one canonical product |
| `pricing` | Price, PriceHistory, ShippingOption, Currency | total price = price − coupon + shipping |
| `offers` | Offer, Coupon, SponsoredPlacement | sponsored never reorders rating-based ranking |
| `reviews` | Review, ReviewVote, ReviewReport, Comment | publish only after moderation |
| `affiliate` | AffiliateProgram, AffiliateNetwork, AffiliateClick, AffiliateConversion, Commission | append-only click log |
| `compliance` | Country, ProductComplianceRule | unknown ⇒ not recommendable |
| `accounts` | User, UserProfile, Favorite, Watchlist, PriceAlert, Notification, consent log | GDPR export/erase |
| `content` | Article, Tag, SEO metadata | editorial workflow, no fake ratings |
| `community` | Thread, Reply, Guide, CommunityDeal, Vote, Follow, Tag, ActivityItem | one moderation pipeline for every type |
| `reputation` | ReputationEvent, Badge, UserBadge, Level | points only for outcomes, never for volume |
| `platform` | AuditLog, Settings, FeatureFlag, RBAC | every privileged action is logged |

## Request paths that matter

**Product page.** `GET /products/{slug}?country=DE&currency=EUR` → catalog read (cached 60 s) +
offers read (cached 30 s, keyed by country) + compliance lookup + price history (cached 15 min).
The compliance lookup runs *before* offers are serialised: a `not_allowed` or
`prescription_only` status returns an empty offer list with a reason object, so no client can
render a purchase CTA.

**Total price.** Computed server-side, never in the client:

```
effective   = price − best_applicable_coupon(price, country)
shipping    = zone(merchant, country).cost, or 0 if effective ≥ merchant.free_over
              or coupon.type = free_shipping
total       = effective + shipping
```

Offers whose merchant has no shipping zone for the selected country are excluded, not shown greyed
out — "does not ship here" is not a comparable offer.

**Outbound click.** `GET /go/{merchant}/{product}` → validate the pair, write `AffiliateClick`
(fire-and-forget to the queue), build the destination URL with tracking parameters, respond 302.
Target p99 under 40 ms; the click write must never block the redirect.

## Caching and invalidation

- Redis, tag-based. Feed import invalidates `offers:{product}` and `shop:{merchant}`.
- Search index updated from the same queue job that commits offer changes.
- Price snapshots are written once per day per (product, merchant) plus on every material change.
- Cache keys always include country and currency; a shared cache across markets is a compliance bug.

## Scaling notes

Stateless web/API pods behind a CDN; workers scale by queue depth. PostgreSQL read replica for
analytics dashboards. `affiliate_click` and `price_history` are partitioned monthly; both are
append-only and dominate write volume. Search is a separate cluster — an index rebuild must never
take the catalogue offline.

## Community read paths

**Community hub.** One aggregate endpoint returns the activity feed (cursor-paginated, muted
authors filtered server-side), trending threads, unanswered questions, top contributors and the
latest approved community deals and guides. Trending is computed in a materialised view refreshed
every few minutes — never per request.

**Thread page.** Thread + replies + accepted answer in one read; the accepted answer is denormalised
onto the thread row so the pinned block needs no second query. Reply counts and last-activity
timestamps are maintained by triggers, not counted on read.

**Feature flags.** `forum_enabled`, `guides_enabled`, `deal_submission_enabled`,
`merchant_qna_enabled`, `public_profiles_enabled` are read with the session and drive both
navigation and endpoint authorisation, so turning a surface off closes its API too.

## Prototype mapping

In this repository the whole stack is collapsed into one streaming component
(`Comparo Performance.dc.html`) with `seed.js` as the database and a logic class as the API and
services. The seams are deliberately the same: lookups (`P`, `M`, `comp`), pricing
(`offerRow`), search (`searchAll`), matching (`matchItem`, `parseFeed`), and mutation
handlers that would map one-to-one onto endpoints in [API.md](API.md).


## Future backend migration of the prototype intelligence layer

Everything in `intel.js` is written as pure functions over the seed graph, so each block maps to one
service with no rewrite of the domain logic.

| Prototype (`intel.js`) | Future service | Trigger | Storage |
| --- | --- | --- | --- |
| `rank()` | **RankingService** | on read, cached per (product, country, weights version) | weight config + explanation payload |
| `trust()`, `trustHistory()` | **TrustService** | nightly recompute + on signal change | `merchant_trust_score`, daily snapshots |
| `risk()` | **RiskService** | on risk event | `merchant_risk_score`, `risk_event` |
| `reviewTrust()`, `dupClusters()`, `burst()`, `manipulation()` | **FraudService** | on review write + nightly re-scan | `review_trust`, `fraud_cluster` |
| `match()`, `clusters()`, `variantGuard()` | **MatchingService** | per feed import (queue worker) | `feed_item_match`, `product_cluster` |
| `histStats()`, `priceConfidence()`, `anomalies()`, `dealScore()` | **PricingService** | on ingest + hourly aggregation | `price_snapshot`, `price_anomaly` |
| `related()`, `similarShops()`, `personal()`, `basket()` | **RecommendationService** | precomputed relations + online assembly | `product_relation`, `user_signal` |
| `affiliateMetrics()`, `commercial()`, `performance()` | **AffiliateService** | click ledger + network ingest | `affiliate_click`, `affiliate_conversion` |
| `automationRuns()`, `tasks()` | **AutomationService** | domain events | `automation_rule`, `automation_log`, `task` |
| `coverage()`, `searchSupply()`, `trends()` | **MarketIntelligenceService** | nightly | `market_coverage`, `demand_signal` |

### Event-driven contracts

`OfferUpdated`, `OfferExpired`, `PriceAnomalyDetected`, `ReviewCreated`, `ReviewFlagged`,
`MerchantRiskChanged`, `MerchantTrustRecomputed`, `ProductMatched`, `ProductMergeRequested`,
`DealExpired`, `CouponReported`, `AffiliateClickRecorded`, `AffiliateLinkBroken`,
`ComplianceStatusChanged`. Each carries entity id, actor, before/after and a correlation id.

### Queue topology

`feed.import` → `matching.resolve` → `price.aggregate` → `ranking.invalidate`;
`risk.analyse`, `fraud.scan`, `notifications.dispatch`, `seo.generate`, `sitemap.build`,
`digest.send`. Ordered per merchant, at-least-once, idempotent by (entity, revision).

### Observability

* **Logs** — structured JSON, correlation id per request and per queue job.
* **Metrics** — feed import duration and error rate, match confidence distribution, anomaly rate,
  ranking cache hit rate, redirect success rate, moderation backlog, alert dispatch latency.
* **Traces** — search → rank → render, and feed import → match → publish.
* **Alerts** — feed uptime below SLA, anomaly rate spike, redirect failures, moderation backlog,
  trust score mass drift (a scoring bug looks exactly like a market event, so it is alerted on).

### Read/write API surfaces

Public read API: products, prices, shops, ratings, deals (documented in API.md).
Internal API: `POST /internal/rank`, `/internal/match`, `/internal/risk`,
`/internal/recommendations` — service-to-service only, never exposed to merchants, and rate-limited
per caller. Merchant API is scoped to the calling merchant: no cross-merchant analytics, no admin
risk scores, no private user data.


## Seed load order (updated)

```
seed.js            base catalogue, offers, reviews, users, affiliate
seed-community.js  extra brands/products/merchants, forum, more reviews
seed-dose.js       mg per serving per ingredient, carriers, NRVs, market dose limits
seed-orders.js     orders — built from verified reviews, then unreviewed purchases
seed-seo.js        entity pages, ingredient entities, editorial context
seed-intel.js  →  intel.js
seed-growth.js →  growth.js
seed-commercial.js → commercial.js
```

`seed-dose.js` must run after `seed-community.js` (it doses all 46 products) and before
`seed-seo.js` (ingredient entity pages read the ranking). `seed-orders.js` must run after
reviews exist, because every verified-purchase review mints the order that backs it.

| Function | Service | When | Table |
|---|---|---|---|
| `cheapestSourceOf()` | **DosingService** | nightly precompute | `ingredient_price_rank` |
| `deliveryStats()` | **OrderService** | hourly aggregation per merchant × market | `merchant_delivery_stats` |
| `confidenceOf()` | **PricingService** | on price write + on report write | `price_confidence` |
