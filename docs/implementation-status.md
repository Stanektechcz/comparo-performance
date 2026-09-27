# Implementation status

Updated 2026-09-26 (Phase 3 — search & discovery). Status values only:
NOT STARTED · IN PROGRESS · FUNCTIONAL · PARITY VERIFIED · PRODUCTION HARDENED.
Nothing below is PRODUCTION HARDENED yet: no production deployment, load test or security audit exists.
No browser (manual or automated E2E) verification of authenticated merchant/staff pages has been run yet
for the Phase 2 feeds/matching UI; the public search page was checked once at 375 px during development
(no screenshots). Coverage below is otherwise Pest Feature/Unit tests only.

**Latest full gate** (2026-09-26, after P4-02/P4-03, Phase 4 wave 1): Pest **2 112 tests** — 2 101 passed,
11 skipped (the Meilisearch contract dataset without a local server), 10 852 assertions, SQLite; Larastan
level 7 — 0 errors; Pint clean; prototype parity `--check` clean; `npm run check` exit 0; `php artisan
comparo:verify-prototype` 137/137.

**Quality gates at Phase 3 Gate C** (2026-09-26): Pest **1 803 tests** — 1 792 passed, 11 skipped (the
Meilisearch contract dataset: no local server at the time) — on SQLite (9 675 assertions) **and**
on PostgreSQL 18.4 (embedded, local; CI uses PostgreSQL 16) (9 672 assertions — driver-specific schema
assertions differ), Pint clean, Larastan level 7 — 0 errors, prototype parity `--check` clean (matching,
anomalies and search fixtures), `npm run types:check` / `check` / `build:ssr` all exit 0,
`php artisan comparo:verify-prototype` 137/137. Git remote `Stanektechcz/comparo-performance` (private)
and GitHub Actions CI (`.github/workflows/tests.yml`: SQLite, PostgreSQL 16 + Meilisearch 1.53.2 service,
frontend) now exist and ran green on PR #1 (merged to main). Meilisearch 1.53.2 has also been verified
locally against a real server: engine contract dataset 48/48.

## Modules

| Module | Prototype coverage | Backend model | API | Frontend | Tests | Status | Known gaps |
|---|---|---|---|---|---|---|---|
| Platform foundation | — | Laravel 13.33, Fortify (register, login, verify, reset, 2FA, passkeys), Sanctum, Horizon, Scout, Boost | `/api/user` (whitelisted) | starter auth + settings pages, Comparo tokens | starter auth suite | FUNCTIONAL | 2FA not yet *required* for staff/merchants; JSON log channel + metrics not configured |
| RBAC (staff) | `/intel → Roles` (demo switcher) | spatie permissions; `Permission` enum (43), `StaffRole` bundles (14) | — | `auth.user.is_staff` | StaffAuthorizationTest | FUNCTIONAL | no staff console UI; role management UI |
| Markets / geography | 27 markets, 12 currencies | countries, currencies, exchange_rates (dated) | `?market=` everywhere | market selector (cookie) | MarketSelectionTest | FUNCTIONAL | rates are demo (fixed, prototype); no ECB import job |
| Canonical catalogue | products, brands, categories, variants, doses | products (+merge fields), variants, ingredients, ingredient_product | via product pages | product/brand/category pages | ProductPageTest, CatalogPagesTest | FUNCTIONAL | merge *workflow* (staff action + audit) not built; only the 301 for merged products |
| Merchants | shops, zones, trust inputs, risk | merchants, merchant_user, shipping zones, trust signals (append-only), risk events | merchant offers API (read-only) | shop pages | MerchantIsolationTest | FUNCTIONAL | merchant dashboard UI (Phase 7) |
| Offers & coupons | offers, coupons, coupon states | merchant_products, offers, coupons, coupon_country | public offers API | offer table/cards | ProductPageTest | FUNCTIONAL | feed ingestion now built (see "Feeds & matching" below); coupon reports workflow still absent |
| Total landed price | `offerRow` | LandedPriceCalculator (integer arithmetic) | yes | yes | LandedPriceParityTest (7 209 cases), unit tests | PARITY VERIFIED | per-basket shipping (basket optimiser) not ported |
| ComparoRank | `rank(ctx)` | RankingService, versioned `ranking_versions` | yes (breakdown, version) | "Why this rank?" dialog | RankingParityTest (3 239 + 21), DatabaseParityTest, RankingPurityTest | PARITY VERIFIED | weight-change action (audited activation) not built |
| Trust Score / Risk | `trust(m)`, `risk(m)` | TrustService, RiskService, MerchantScores | trust only (public signals) | trust badge/dialog | MerchantScoresParityTest | PARITY VERIFIED | trust inputs are imported demo measurements; real measurement jobs pending |
| Price confidence / reference-price check | `priceConfidence`, `fakeDiscount` | PriceConfidenceService, PriceHistoryAnalyzer | yes | yes | ProductScoresParityTest | PARITY VERIFIED | shopper reports (`confidenceOf`) not ported |
| Price history & intelligence | `histStats`, badge, timing, forecast | price_snapshots (per offer, append-only, DB triggers), market_price_stats | via product page | chart + data table + tiles | ProductScoresParityTest, AppendOnlyHistoryTest | FUNCTIONAL | daily aggregation job (snapshots → market_price_stats) not built; demo series labelled |
| Compliance | `comp()` + banners | product_compliance_rules; missing rule = unknown | `meta.compliance`, excluded counts | compliance banner states | ComplianceStatusTest, ProductPageTest | FUNCTIONAL | deliberate deviation from prototype (ADR 0007); staff review queue UI |
| Product page | `buildProduct` | ProductOfferComparison + presenters | — | full page, responsive, a11y dialogs | ProductPageTest | FUNCTIONAL | reviews/community sections (Phase 4/9); alerts/save/compare intentionally absent |
| Public offers API v1 | `GET /products/{slug}/offers` | presenter (snake_case) | `/api/public/v1/products/{slug}/offers` | — | PublicOffersApiTest | FUNCTIONAL | API keys/entitlements (Phase 10) |
| SEO head / SSR | per-page meta, JSON-LD | SeoPresenter; Blade fallback + Inertia `<Head>` | — | seo-head | ProductPageTest (SEO head) | FUNCTIONAL | sitemaps, robots route, llms.txt, RSS, hreflang per market (Phase 12) |
| Demo data | all seeds | PrototypeSnapshotImporter + Demo*Seeders (time-anchored, idempotent); importer also imports brand aliases and listed ingredients, not just core Phase 1 entities | — | — | DemoDataSeederTest | FUNCTIONAL | only Phase 1 entities imported |
| Parity harness | intel.js, HTML engines | tools/prototype-parity/export-fixtures.mjs | — | — | `--check` in CI | FUNCTIONAL | matching, reviews and delivery fixtures are ported (see rows below); only dosing fixtures remain unported |
| Audit log | `S.auditLog` | `Platform\Audit\AuditLogger`/`AuditRedactor`, `AuditAction` (18 cases); audit_logs (append-only, DB trigger, actor FK decoupled from `users` — ADR-0014) | — | — | AppendOnlyHistoryTest; assertions inline in Feeds/Matching Feature tests | FUNCTIONAL | no dedicated audit-log viewer page yet; only Feeds/Matching actions are audited so far, not every privileged action platform-wide |
| Feature flags | — (new, A-17) | `Platform\Features\{Feature,FeatureFlags}`, config-backed (`config/features.php`), fail-closed via `feature:` route middleware | shared Inertia prop (`clientFlags()`) | gates `/merchant/*` (404 when off) | route-level feature tests | FUNCTIONAL | config-only (no DB-backed store); Pennant deferred |
| Search & discovery | search, synonyms, facets, header suggest | `App\Domain\Search\**` (see `docs/modules/search.md`), `SearchServiceProvider` (engine binding by `scout.driver`) | `search`, `search.clicks`, `api.public.v1.search.suggest` | `resources/js/pages/search/index.tsx`, header suggest combobox | `tests/Unit/Search/**`, `tests/Feature/Search/**`, `tests/Unit/Parity/{SearchParityTest,SearchSensitivityTest}.php`, `tests/Architecture/{SearchBoundariesTest,SearchJobsTest}.php` | `DatabaseSearchEngine` relevance: **PARITY VERIFIED**; overall module: FUNCTIONAL | Meilisearch adapter now verified against a real local server (engine contract dataset 48/48) and against the CI service (GitHub Actions ran green on PR #1); no browser check of the search page; cold-cache fan-out cost (F-15) and indexing batch/chunk sizing (F-17) load-tested and RESOLVED — see BACKLOG.md; ProductMarketSnapshot still issues one offer comparison per product × market instead of the bulk path (F-18, open); did-you-mean absent on Meilisearch (falls straight to "browse categories") |
| Search analytics | zero-result demand, click attribution | `Search\Analytics\{RecordSearch,RecordSearchClick,QueryRedactor,SessionHasher,AggregateSearchDemand,SearchDemandReport,PruneSearchAnalytics}` | — | — | `tests/Feature/Search/Analytics*Test.php` | FUNCTIONAL | k-threshold sums daily sessions rather than deduplicating across days (weaker than distinct-session k-anonymity), DPO sign-off pending (F-16, A-24); D-09 retention periods are safe defaults, not signed off |
| Multi-currency comparison | — (prototype markets are single-currency) | `Pricing\Currency\{ExchangeRates,ComparisonRates}`, `Pricing\LandedPrice\MerchantTermsConverter` (ADR-0017) | embedded in product page + search props, `meta.market_min_currency` | product page, search result cards | `tests/Unit/Pricing/**`, `tests/Feature/Search/IndexingMarketCurrencyTest.php` | FUNCTIONAL (single-currency paths: **PARITY VERIFIED**, byte-identical to the prototype) | no browser check; real ECB rate import still pending (D-06) — `exchange_rates` is demo data |
| Feeds & matching (pipeline) | Feed Match Center | `App\Domain\Feeds\**`, `App\Domain\Matching\**` (see `docs/modules/{feeds,matching}.md`) | — | — | `tests/Feature/Feeds/**`, `tests/Feature/Matching/**`, `tests/Architecture/{FeedJobsTest,MatchingBoundariesTest}.php` | FUNCTIONAL (matching engine itself: **PARITY VERIFIED**, see below) | purchase links still go directly to merchants until Phase 5; feed-level shipping stored raw only (D-07); availability-map editing not in the merchant form; `api_push` transport not implemented; canonical-product creation from an approved candidate deferred to Phase 8; no notification on run completion/failure |
| Matching engine (`intel.js` Engine 2.0 port) | `matchItem` / Product Matching Engine 2.0 | `Matching\Engine\ProductMatcher` + `MatchingPolicy` (`matching_policies`, versioned) | — | — | `tests/Unit/Matching/ProductMatcherTest.php`, `tests/Unit/Parity/{MatchingParityTest,MatchingSensitivityTest}.php`, `tests/Feature/Parity/DatabaseMatchingParityTest.php` | PARITY VERIFIED | candidate narrowing is a documented deviation (ADR-0012 #3), proven bounded on the full catalogue |
| Merchant portal — feeds | (simulated in prototype only) | `Feeds\Actions\**` behind `FeedSourcePolicy` | — | `resources/js/pages/merchant/feeds/**` | `tests/Feature/Merchant/MerchantFeedsTest.php`, `tests/Feature/Feeds/**` | FUNCTIONAL | no dedicated notification on run completion/failure |
| Merchant portal — matching | (simulated in prototype only) | `Matching\Queries\MerchantMatchingQueue`, `Matching\Actions\{DecideMatch,ProposeProductCandidate}` behind `MerchantProductPolicy` | — | `resources/js/pages/merchant/matching/**` | `tests/Feature/Merchant/MerchantMatchingTest.php`, `tests/Feature/Matching/**` | FUNCTIONAL | — |
| Staff console — catalogue matching only | `/intel` admin views | `Admin\Catalogue\{MatchingQueueController,MatchingListingController,ListingDecisionController,ListingRematchController,CandidateResolutionController,ConflictResolutionController,ProductSearchController}` behind `matching.review`(+`offers.manage`) | — | `resources/js/pages/admin/catalogue/matching/**` | `tests/Feature/Admin/CatalogueMatchingTest.php` | FUNCTIONAL | only the matching queue exists in the staff console; no other staff console area is built (merchants, compliance, SEO, etc. remain Phase 8 scope) |
| Offers write path & events | `PublishOffer`/`RecordPriceSnapshot`/`DeactivateOffer`/`ConfirmListingsSeen`; `OfferPublished`/`OfferDeactivated`/`OfferRelinked`/`PriceChanged`/`ProductMatched` events | `App\Domain\Offers\Actions\**`, `App\Domain\Pricing\Actions\**`, `App\Domain\Platform\Listeners\BumpProductCacheVersion` | — | offer changes visible on next product page view (cache-bumped) | `tests/Feature/Feeds/FeedPublishingTest.php`, event-level assertions across Feeds/Matching Feature tests | FUNCTIONAL | snapshot-writing-inside-transaction amends ADR-0003 (ADR-0013); no dedicated event/notification consumer for `FeedImported`/`FeedFailed` yet |
| Reviews / verification / orders | reviews, proofs, orders | schema (5 migrations, 15 models, 16 enums, factories, append-only triggers) + pure engines with exact parity (ReviewTrust, ReviewWeight, RatingAggregator, abuse signals, DeliveryStatsCalculator) | — | rating summary only | `tests/Unit/{Reviews,Orders}` | IN PROGRESS | Phase 4 (wave 1 done: P4-02/P4-03; domain actions, HTTP/UI, moderation and verification still to build) |
| Affiliate redirect & conversions | `#/go`, reconciliation | — | — | purchase links go directly to the merchant URL | — | NOT STARTED | Phase 5 (`/go/{merchant}/{product}`) |
| Account: saved, compare, basket, alerts | yes | — | — | — | — | NOT STARTED | Phase 6 |
| Merchant dashboard | yes | membership + read-only offers API | partial | — | — | NOT STARTED | Phase 7 |
| Staff console | admin, intel, SEO | permissions only; Horizon gated | — | — | — | NOT STARTED | Phase 8 |
| Community / reputation / live / gamification | yes | — | — | — | — | NOT STARTED | Phase 9 |
| Commercial OS | yes | — (commercial data deliberately not imported) | — | — | — | NOT STARTED | Phase 10 |
| Growth OS | yes | — | — | — | — | NOT STARTED | Phase 11 |
| GDPR export/erase | yes | — | — | — | — | NOT STARTED | — |
| Staging environment | — (new, A-39) | `tools/staging/{smoke,local-stack}.mjs`, `.env.staging.example` | — | — | smoke script | FUNCTIONAL locally | no hosted staging server yet (domain/TLS/hosting decision — see EXTERNAL-DEPENDENCIES.md) |

## Invariant coverage

| Invariant | Enforced by |
|---|---|
| Commercial money never changes organic rank | closed `RankingFactor` enum, `RankingContext` whitelist, `RankingPurityTest` (incl. `commercial_spend_does_not_change_organic_rank`: signature + schema check), arch test (no Commercial/Affiliate/Models deps) |
| Compliance before serialization | `ComplianceStatus` policy; blocked → no offers serialized (page, API, JSON-LD); unknown → no purchase links; tests in ProductPageTest / PublicOffersApiTest |
| Append-only history | model guard + DB triggers (SQLite + PostgreSQL); AppendOnlyHistoryTest |
| Merchant isolation | scoped `MerchantOffers` query + `OfferPolicy`; MerchantIsolationTest (`merchant_a_cannot_view_merchant_b_offer`, `merchant_a_cannot_edit_merchant_b_offer`, `merchant_cannot_access_internal_risk_score`); **now also covers every `/merchant/*` feed and matching route** — `{feed}`/`{run}`/`{listing}` resolved through `MerchantContext`-scoped queries (foreign id → 404) before `FeedSourcePolicy`/`MerchantProductPolicy`, negative tests in `tests/Feature/Merchant/{MerchantFeedsTest,MerchantMatchingTest}.php` including a user who belongs to merchants A and B |
| Pure scoring | PureServicesTest (no DB/facades/clock/randomness; readonly DTOs); `Matching\Engine\ProductMatcher` and `Shared\Text\TextFold`/`TitleSimilarity` are pure by the same rule |
| Explicit serialization | presenters/resources only; whitelisted `auth.user`; outbound URLs restricted to http(s) |
| Audit of privileged actions | `Platform\Audit\AuditLogger::record()` called inside the same transaction as the write it describes (ADR-0014); covers every Feeds/Matching action listed in the "Feeds & matching" row above — no longer "first privileged action, Phase 8" |
| Matching append-only decisions | Eloquent guard + DB triggers on `matching_decisions` (`Platform\Exceptions\AppendOnlyViolation`), linear chain via `supersedes_id` (ADR-0012) |
| Price snapshot written inside the publish transaction | `Offers\Actions\PublishOffer` calls `Pricing\Actions\RecordPriceSnapshot` in the same DB transaction, never from a listener (ADR-0013, amends ADR-0003) |

Not yet covered (the entities do not exist yet): `merchant_a_cannot_view_merchant_b_invoice`,
`merchant_a_cannot_view_merchant_b_api_key` (Phase 10), `restricted_product_cannot_activate_sponsored_campaign`
(Phase 10).

## Commands used for the gates

```bash
php artisan test --compact
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=2G
node tools/prototype-parity/export-fixtures.mjs --check
npm run types:check && npm run check && npm run build:ssr
```
