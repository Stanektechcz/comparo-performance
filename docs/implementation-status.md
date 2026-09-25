# Implementation status

Updated 2026-09-25 (end of the Phase 0 + first Phase 1 slice run). Status values only:
NOT STARTED · IN PROGRESS · FUNCTIONAL · PARITY VERIFIED · PRODUCTION HARDENED.
Nothing below is PRODUCTION HARDENED yet: no production deployment, load test or security audit exists.

**Quality gates at the end of this run** (see the final section for exact commands): Pest 136 tests /
2 715 assertions green on SQLite **and** on PostgreSQL 18.4 (embedded, local; CI uses PostgreSQL 16),
Pint clean, Larastan level 7 — 0 errors, prototype parity `--check` clean, `npm run types:check` /
`check` / `build` / `build:ssr` green, SSR server-renders the product page body. Prototype files:
137/137 SHA-256 unchanged.

## Modules

| Module | Prototype coverage | Backend model | API | Frontend | Tests | Status | Known gaps |
|---|---|---|---|---|---|---|---|
| Platform foundation | — | Laravel 13.33, Fortify (register, login, verify, reset, 2FA, passkeys), Sanctum, Horizon, Scout, Boost | `/api/user` (whitelisted) | starter auth + settings pages, Comparo tokens | starter auth suite | FUNCTIONAL | 2FA not yet *required* for staff/merchants; JSON log channel + metrics not configured |
| RBAC (staff) | `/intel → Roles` (demo switcher) | spatie permissions; `Permission` enum (43), `StaffRole` bundles (14) | — | `auth.user.is_staff` | StaffAuthorizationTest | FUNCTIONAL | no staff console UI; role management UI |
| Markets / geography | 27 markets, 12 currencies | countries, currencies, exchange_rates (dated) | `?market=` everywhere | market selector (cookie) | MarketSelectionTest | FUNCTIONAL | rates are demo (fixed, prototype); no ECB import job |
| Canonical catalogue | products, brands, categories, variants, doses | products (+merge fields), variants, ingredients, ingredient_product | via product pages | product/brand/category pages | ProductPageTest, CatalogPagesTest | FUNCTIONAL | merge *workflow* (staff action + audit) not built; only the 301 for merged products |
| Merchants | shops, zones, trust inputs, risk | merchants, merchant_user, shipping zones, trust signals (append-only), risk events | merchant offers API (read-only) | shop pages | MerchantIsolationTest | FUNCTIONAL | merchant dashboard UI (Phase 7) |
| Offers & coupons | offers, coupons, coupon states | merchant_products, offers, coupons, coupon_country | public offers API | offer table/cards | ProductPageTest | FUNCTIONAL | feed ingestion (Phase 2); coupon reports workflow |
| Total landed price | `offerRow` | LandedPriceCalculator (integer arithmetic) | yes | yes | LandedPriceParityTest (7 209 cases), unit tests | PARITY VERIFIED | per-basket shipping (basket optimiser) not ported |
| ComparoRank | `rank(ctx)` | RankingService, versioned `ranking_versions` | yes (breakdown, version) | "Why this rank?" dialog | RankingParityTest (3 239 + 21), DatabaseParityTest, RankingPurityTest | PARITY VERIFIED | weight-change action (audited activation) not built |
| Trust Score / Risk | `trust(m)`, `risk(m)` | TrustService, RiskService, MerchantScores | trust only (public signals) | trust badge/dialog | MerchantScoresParityTest | PARITY VERIFIED | trust inputs are imported demo measurements; real measurement jobs pending |
| Price confidence / reference-price check | `priceConfidence`, `fakeDiscount` | PriceConfidenceService, PriceHistoryAnalyzer | yes | yes | ProductScoresParityTest | PARITY VERIFIED | shopper reports (`confidenceOf`) not ported |
| Price history & intelligence | `histStats`, badge, timing, forecast | price_snapshots (per offer, append-only, DB triggers), market_price_stats | via product page | chart + data table + tiles | ProductScoresParityTest, AppendOnlyHistoryTest | FUNCTIONAL | daily aggregation job (snapshots → market_price_stats) not built; demo series labelled |
| Compliance | `comp()` + banners | product_compliance_rules; missing rule = unknown | `meta.compliance`, excluded counts | compliance banner states | ComplianceStatusTest, ProductPageTest | FUNCTIONAL | deliberate deviation from prototype (ADR 0007); staff review queue UI |
| Product page | `buildProduct` | ProductOfferComparison + presenters | — | full page, responsive, a11y dialogs | ProductPageTest | FUNCTIONAL | reviews/community sections (Phase 4/9); alerts/save/compare intentionally absent |
| Public offers API v1 | `GET /products/{slug}/offers` | presenter (snake_case) | `/api/public/v1/products/{slug}/offers` | — | PublicOffersApiTest | FUNCTIONAL | API keys/entitlements (Phase 10) |
| SEO head / SSR | per-page meta, JSON-LD | SeoPresenter; Blade fallback + Inertia `<Head>` | — | seo-head | ProductPageTest (SEO head) | FUNCTIONAL | sitemaps, robots route, llms.txt, RSS, hreflang per market (Phase 12) |
| Demo data | all seeds | PrototypeSnapshotImporter + Demo*Seeders (time-anchored, idempotent) | — | — | DemoDataSeederTest | FUNCTIONAL | only Phase 1 entities imported |
| Parity harness | intel.js, HTML engines | tools/prototype-parity/export-fixtures.mjs | — | — | `--check` in CI | FUNCTIONAL | reviews, matching, dosing, delivery fixtures exported but not yet ported |
| Audit log | `S.auditLog` | audit_logs (append-only, DB trigger) | — | — | AppendOnlyHistoryTest | IN PROGRESS | no AuditLogger service and no audited privileged action exists yet |
| Search | search, synonyms, facets | Scout + meilisearch-php installed, config only | — | — | — | NOT STARTED | Phase 3 |
| Feeds & matching | Feed Match Center | — | — | — | fixtures only | NOT STARTED | Phase 2 (next slice) |
| Reviews / verification / orders | reviews, proofs, orders | — (derived ratings imported, labelled) | — | rating summary only | fixtures only | NOT STARTED | Phase 4 |
| Affiliate redirect & conversions | `#/go`, reconciliation | — | — | purchase links go directly to the merchant URL | — | NOT STARTED | Phase 5 (`/go/{merchant}/{product}`) |
| Account: saved, compare, basket, alerts | yes | — | — | — | — | NOT STARTED | Phase 6 |
| Merchant dashboard | yes | membership + read-only offers API | partial | — | — | NOT STARTED | Phase 7 |
| Staff console | admin, intel, SEO | permissions only; Horizon gated | — | — | — | NOT STARTED | Phase 8 |
| Community / reputation / live / gamification | yes | — | — | — | — | NOT STARTED | Phase 9 |
| Commercial OS | yes | — (commercial data deliberately not imported) | — | — | — | NOT STARTED | Phase 10 |
| Growth OS | yes | — | — | — | — | NOT STARTED | Phase 11 |
| Feature flags, GDPR export/erase | yes | — | — | — | — | NOT STARTED | — |

## Invariant coverage

| Invariant | Enforced by |
|---|---|
| Commercial money never changes organic rank | closed `RankingFactor` enum, `RankingContext` whitelist, `RankingPurityTest` (incl. `commercial_spend_does_not_change_organic_rank`: signature + schema check), arch test (no Commercial/Affiliate/Models deps) |
| Compliance before serialization | `ComplianceStatus` policy; blocked → no offers serialized (page, API, JSON-LD); unknown → no purchase links; tests in ProductPageTest / PublicOffersApiTest |
| Append-only history | model guard + DB triggers (SQLite + PostgreSQL); AppendOnlyHistoryTest |
| Merchant isolation | scoped `MerchantOffers` query + `OfferPolicy`; MerchantIsolationTest (`merchant_a_cannot_view_merchant_b_offer`, `merchant_a_cannot_edit_merchant_b_offer`, `merchant_cannot_access_internal_risk_score`) |
| Pure scoring | PureServicesTest (no DB/facades/clock/randomness; readonly DTOs) |
| Explicit serialization | presenters/resources only; whitelisted `auth.user`; outbound URLs restricted to http(s) |

Not yet covered (the entities do not exist yet): `merchant_a_cannot_view_merchant_b_invoice`,
`merchant_a_cannot_view_merchant_b_api_key` (Phase 10), `restricted_product_cannot_activate_sponsored_campaign`
(Phase 10), "privileged status change creates an AuditLog" (first privileged action, Phase 8).

## Commands used for the gates

```bash
php artisan test --compact
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=2G
node tools/prototype-parity/export-fixtures.mjs --check
npm run types:check && npm run check && npm run build:ssr
```
