# Migration roadmap

Phased port of the Comparo Performance prototype to the Laravel 13 modular monolith
([target-laravel-architecture.md](target-laravel-architecture.md)). Each phase is a vertical slice that
ends in working, tested software; later phases only add.

Status as of 2026-09-26: **Phases 0–3 DONE (Gate C on each). Phase 4 IN PROGRESS (wave 1 landed:
schema + pure engines with parity + platform foundations). Phases 5–13 not started.**

## Overview

| Phase | Name | Main contexts | Status |
|---|---|---|---|
| 0 | Foundation | Platform, Accounts, tooling | DONE |
| 1 | Catalogue, offers, landed price, compliance, ranking — product page | Catalog, Merchants, Offers, Pricing, Compliance | DONE |
| 2 | Feeds and matching | Feeds, Matching | DONE |
| 3 | Search | Search | DONE |
| 4 | Reviews, purchase verification, orders | Reviews | IN PROGRESS (next slice) |
| 5 | Affiliate redirect and conversions | Affiliate | NOT STARTED |
| 6 | Accounts: saves, alerts, notifications | Engagement | NOT STARTED |
| 7 | Merchant portal | Merchants, Feeds, Offers | NOT STARTED |
| 8 | Staff consoles | Compliance, Merchants, Reviews, Platform | NOT STARTED |
| 9 | Community and reputation | Community | NOT STARTED |
| 10 | Commercial (plans, subscriptions, billing, sponsored, API plans) | Commercial | NOT STARTED |
| 11 | Growth | Growth | NOT STARTED |
| 12 | Content and SEO | Content | NOT STARTED |
| 13 | Platform hardening and prototype cleanup release | Platform | NOT STARTED |

---

## Phase 0 — Foundation (IN PROGRESS)

| Scope | Status |
|---|---|
| Laravel 13.33 React starter kit (Inertia 3, React 19, TS, Tailwind 4, Vite+, Fortify incl. passkeys/2FA, Wayfinder) | done |
| Sanctum, spatie/laravel-permission 8, Scout 11 + Meilisearch, Horizon 5, predis, Flysystem S3 | installed |
| Laravel Boost 2.10 (`AGENTS.md`, `.mcp.json`, `.claude/skills`) | done |
| Prototype excluded from lint/format (`vite.config.ts`), SHA-256 integrity checks | done |
| Architecture maps (`docs/architecture/*.md`), contradictions, open decisions | done |
| Parity harness (`tools/prototype-parity/export-fixtures.mjs`), fixtures, seed snapshot | done |
| ADRs 0001–0011 | done |
| `compose.yaml` (PostgreSQL 16, Redis 7, Meilisearch, MinIO, Mailpit), `.env.example` with fallbacks | done |
| Staff permission catalogue + role bundles + seeder | done (`Permission`, `StaffRole`, `RolesAndPermissionsSeeder`) |
| Correlation-id middleware | done (prepended globally in `bootstrap/app.php`) |
| Horizon gate on `staff.horizon.view` | done (`HorizonServiceProvider`) |
| JSON log channel | pending |
| CI: PostgreSQL job, `export-fixtures.mjs --check`, architecture tests | done (`.github/workflows/tests.yml`; ran green on PR #1) |

**Exit criteria**

- `php artisan test --compact`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`,
  `npm run types:check`, `npm run check` pass locally and in CI.
- CI runs migrations and tests against PostgreSQL and runs the parity `--check`.
- Architecture tests guard layering and ranking purity.
- Prototype file hashes unchanged.

---

## Phase 1 — Catalogue, offers, landed price, compliance, ranking (IN PROGRESS)

The product page with a correct, compliance-filtered, ranked offer table is the whole product in
miniature.

| Scope | Status |
|---|---|
| Phase 1 migrations (`2026_09_25_1000xx`–`1007xx`): geography, catalogue, merchants, offers, coupons, compliance, price history, ranking, audit | done |
| Eloquent models (`app/Models`) incl. `AppendOnly` guard | done |
| Pure services + parity: ranking, landed price, market stats, trust, risk, completeness, price history, price confidence | done (see ADR-0010) |
| `Money`, `JsMath` | done |
| Compliance status policy (`ComplianceStatus`, `ComplianceDecision`) | done |
| Market resolver (`?market` → cookie → default): `MarketResolver`, `ResolveMarket` middleware | done (web and api middleware groups) |
| Query objects: `ProductOfferComparison`, `ComplianceResolver`, `MerchantScores`, `ActiveRankingWeights`, `ProductPriceHistory`, `ExchangeRates` | code exists |
| Compliance applied before presenters (no offers unless visible; purchase URL only if purchasable) | code exists; status × surface test matrix pending |
| Controllers (`Catalog/*`, `Api/PublicV1/ProductOffersController`, `MarketController`) and presenters (`app/Http/Presenters/*`, incl. `SeoPresenter`) | routes (`routes/web.php`, `routes/api.php`), pages (`resources/js/pages/catalog/*`) and Blade `seo` head rendering exist; end-to-end verification against the demo snapshot pending |
| Demo importer `PrototypeSnapshotImporter` (time-anchored via `TimeShift`, gated by `DemoEnvironment`) + `Demo*Seeder` incl. demo personas | code exists |
| Offer comparison cache (`CacheKeys`, `CatalogCacheVersion` version tokens) | done — `Platform\Listeners\BumpProductCacheVersion` bumps tokens after commit |
| Architecture tests for `RankingContext` and layering | exist (`tests/Architecture/*`) |

**Exit criteria**

- `GET /products/{slug}` renders via SSR for every seed product in every active market.
- Offer table order, scores, totals and badges equal the prototype for the demo snapshot (parity tests
  plus a feature test comparing presenter output to fixtures).
- Compliance matrix test (5 statuses × product page, offers API, cache) passes; `unknown` has no purchase
  links; blocked products serialize no offers.
- Price history is written only as new rows; update/delete attempts fail on SQLite and PostgreSQL.
- Every ranking explanation carries version and evaluatedAt.
- Offers endpoint < 120 ms p95 on the demo dataset with a warm cache.

---

## Phases 2–13

| Phase | Scope | Exit criteria | Status |
|---|---|---|---|
| 2 Feeds and matching | Feed sources, runs, items; parsing (CSV/XML/JSON); normalisation; matching port; manual match queue; offer upsert and snapshots | 10 000-row feed imports < 60 s; `matching.json` parity; unmatched items never create offers; re-import is idempotent | DONE |
| 3 Search | Scout + Meilisearch indexes (products, brands, merchants), synonyms (`kreatin` = `creatine`), suggest, zero-result logging, database driver fallback | Suggest < 80 ms p95; blocked products absent from market-scoped results; index rebuild command | DONE |
| 4 Reviews, verification, orders | `orders`, `purchase_proofs` with moderation queue, click-match verification, credibility weighting (`reviews.json` parity), weighted aggregates | Weighted rating equals prototype; unverified reviews visible with lower weight; no raw verification email stored | IN PROGRESS (schema + pure engines done; domain actions/HTTP/UI pending) |
| 5 Affiliate | `/go/{merchant}/{product}` with Redis click buffer, `FlushClickBuffer`, conversion webhooks, reconciliation | Redirect < 50 ms p95; compliance-blocked/unknown never redirect; webhook HMAC and idempotency tested | NOT STARTED |
| 6 Accounts | Saves, lists, follows, alerts with nightly evaluation, notifications, preferences | Alert triggers derived from real totals; no seeded counters | NOT STARTED |
| 7 Merchant portal | Feed health, match center, offers, deals, analytics, benchmarks, merchant API tokens | Negative isolation test per endpoint; benchmarks anonymised | NOT STARTED |
| 8 Staff consoles | Moderation, compliance matrix and review queue, merchant approval, risk, anomalies, product merge, link health, audit viewer | Every action permission-checked and audited | NOT STARTED |
| 9 Community and reputation | Forum, guides, reputation, juries | Single points currency decided (C-31); jury draw reproducible | NOT STARTED |
| 10 Commercial | Plans, entitlements, subscriptions, invoices, credit notes, sponsored campaigns, API products/metering; `BillingProvider` with Stripe + manual (ADR-0006) | No Stripe import outside the provider; D-04/D-05 decided before real charges; promotion never alters organic rank | NOT STARTED |
| 11 Growth | Opportunity engine over live data, tasks, experiments | Opportunities derived, never stored by hand; seeded analytics removed or labelled (D-16) | NOT STARTED |
| 12 Content and SEO | Page registry, metadata, programmatic pages, sitemaps, hreflang for enabled locales, newsletter, research | Sitemaps nightly; JSON-LD obeys compliance; Core Web Vitals targets met | NOT STARTED |
| 13 Platform hardening | GDPR export/erase, retention jobs (D-09), partitioning of `price_snapshots`/`affiliate_clicks`, security headers, load tests, **prototype cleanup release** (removal of root prototype files after sign-off) | Performance targets met under load; retention jobs verified; prototype removed only with an explicit release decision | NOT STARTED |

---

## Phase 2 — feeds and matching (DONE; kept for reference)

Superseded as "next slice" — Phase 2 shipped at Gate C (see [`docs/architecture/phase-2-feeds-matching.md`](phase-2-feeds-matching.md)
and [`TASK-GRAPH.md`](../autonomy/TASK-GRAPH.md)). The current next slice is Phase 4 — reviews,
verification and orders (see [`phase-4-reviews-orders.md`](phase-4-reviews-orders.md) and
[`TASK-GRAPH.md`](../autonomy/TASK-GRAPH.md)).

### Entities

| Model | Table | Key fields |
|---|---|---|
| `FeedSource` | `feed_sources` | `merchant_id`, `format` (csv/xml/json/api), `url` or upload path, `schedule`, `field_map` (json), `is_active`, `last_run_at` |
| `FeedRun` | `feed_runs` | `feed_source_id`, `status` (queued/running/succeeded/failed/partial), `started_at`, `finished_at`, `rows_total`, `rows_valid`, `rows_rejected`, `matched_auto`, `matched_confirm`, `unmatched`, `error`, `correlation_id` |
| `FeedItem` | `feed_items` | `feed_run_id`, `merchant_id`, `merchant_sku`, `raw` (json), `title`, `ean`, `brand_raw`, `pack_raw`, `variant_raw`, `price_minor`, `currency`, `availability`, `url`, `status` (valid/rejected/compliance_hold), `rejection_reason` |

`price_snapshots.feed_run_id` already exists (nullable, no FK yet) and will reference `feed_runs`.

### Queue chain (`feed-import` → `matching` → `pricing`)

```
ImportFeed(feedSource)            download/stream, create FeedRun         unique per feed_source_id
 → ParseFeed(feedRun)             rows → FeedItem (validated)
 → NormaliseFeedItems(feedRun)    brand/pack/variant normalisation, currency check
 → MatchFeedItems(feedRun)        MatchingService per item → bucket auto / confirm / unmatched
 → UpsertOffers(feedRun)          auto-matched → merchant_products.product_id, offers
 → RecordPriceSnapshots(feedRun)  append snapshots for changed offers (reason, source = feed)
 → RecalculateRanks(feedRun)      affected product × market
 → EmitFeedImported(feedRun)      FeedImported event after commit (or FeedFailed)
```

### Matching port

| Item | Detail |
|---|---|
| Service | `App\Domain\Matching\MatchingService` (pure): `similarity()`, `match(FeedItemFacts, ProductCandidate[])` |
| Algorithm | `intel.js match()` as mapped in [scoring-engines-map.md](scoring-engines-map.md) §7: EAN +50, brand exact/alias +15 or in title +9, title similarity ×22, pack +10/+6/−12, variant +7, ingredient +5; clamp 0–100 |
| Buckets | `≥ 90` auto, `≥ 65` confirm, else unmatched; `compliance_hold` rows go to conflicts |
| Parity | `tests/Fixtures/PrototypeParity/matching.json` (22 feed items: score, level, bucket, product, parts) |
| Candidate loading | Query object narrows candidates (EAN, brand) before scoring, so large catalogues stay fast; parity tests score against the full seed catalogue |

### Exit criteria for the slice

- `matching.json` parity passes; mutation check proves sensitivity.
- A demo feed import produces the same offers as the prototype for auto-matched items.
- Confirm/unmatched items are visible in a staff/merchant queue and create no offers.
- Re-running the same feed creates no duplicate offers and no snapshot for unchanged prices.
