# Feature matrix — prototype → Laravel (Phases 0–2)

Statuses: `NOT STARTED` · `IN PROGRESS` · `FUNCTIONAL` · `PARITY VERIFIED` · `PRODUCTION HARDENED`.
Later phases (3+: purchase links/`/go`, review moderation, ranking lab, billing, live rooms, …) are all
`NOT STARTED` and out of this matrix's scope — see `docs/architecture/migration-roadmap.md`.

| Prototype feature | Laravel route(s) | Backend service | Entity | Page | Tests | Status |
|---|---|---|---|---|---|---|
| Product catalogue browse/search | `products.index`, `products.show`, `categories.*`, `brands.*` | Catalog queries | `products`, `categories`, `brands` | `resources/js/pages/products/*`, `categories/*`, `brands/*` | `tests/Feature/Catalog/**` | FUNCTIONAL |
| Merchant shop pages | `shops.index`, `shops.show` | Catalog queries | `merchants` | `resources/js/pages/shops/*` | `tests/Feature/Catalog/**` | FUNCTIONAL |
| Best-buy ranking | (embedded in product page props) | `Offers\Ranking\RankingService` | `ranking_versions`, offers | product page | `tests/Unit/Ranking/**`, `tests/Feature/Parity/**` | PARITY VERIFIED |
| Landed price / coupons | (embedded in product page props) | `Pricing\LandedPrice\LandedPriceCalculator` | `coupons`, `coupon_country` | product page | `tests/Unit/Pricing/**` | PARITY VERIFIED |
| Price history / trend / badge | (embedded in product page props) | `Pricing\History\PriceHistoryAnalyzer` | `price_snapshots`, `market_price_stats` | product page | `tests/Unit/Pricing/**` | PARITY VERIFIED |
| Compliance gate (`comp()`) | (server-side, all product/offer surfaces) | `Compliance\Queries\ComplianceResolver` | `product_compliance_rules` | all catalogue pages | `tests/Feature/Compliance/**` | FUNCTIONAL |
| **Merchant feed creation & config** | `merchant.feeds.{index,create,store,show,edit,update}` | `Feeds\Actions\{CreateFeedSource,UpdateFeedSource}` | `feed_sources` | `resources/js/pages/merchant/feeds/{index,create,edit,show}.tsx` | `tests/Feature/Feeds/FeedSourceActionsTest.php`, `tests/Feature/Merchant/MerchantFeedsTest.php` | FUNCTIONAL |
| **Feed field mapping** | `merchant.feeds.mapping.{edit,update}` | `Feeds\Actions\SaveFeedMapping` | `feed_mappings` | `resources/js/pages/merchant/feeds/mapping.tsx` | `tests/Feature/Feeds/FeedSourceActionsTest.php` | FUNCTIONAL |
| **Feed credentials** | `merchant.feeds.credentials.{confirm,update}` | `Feeds\Actions\UpdateFeedCredentials` | `feed_sources.credentials` | (modal on show page) | `tests/Feature/Feeds/FeedSourceActionsTest.php` | FUNCTIONAL |
| **Feed status (pause/resume)** | `merchant.feeds.status.update` | `Feeds\Actions\ChangeFeedSourceStatus` | `feed_sources.status` | `resources/js/pages/merchant/feeds/show.tsx` | `tests/Feature/Feeds/FeedLifecycleTest.php` | FUNCTIONAL |
| **Manual feed run / cancel** | `merchant.feeds.runs.{store,cancel}` | `Feeds\Actions\{StartFeedRun,CancelFeedRun}` | `feed_runs` | `resources/js/pages/merchant/feeds/runs/show.tsx` | `tests/Feature/Feeds/StartFeedRunTest.php`, `FeedCommandsTest.php` | FUNCTIONAL |
| **Feed upload** | `merchant.feeds.uploads.store` | `Feeds\Actions\StoreFeedUpload` | `feed_sources` (transport=upload), private disk | `resources/js/pages/merchant/feeds/show.tsx` | `tests/Feature/Feeds/*` | FUNCTIONAL |
| **Feed fetch → parse → normalise → match → publish pipeline** | (job pipeline, no direct route) | `Feeds\Jobs\{FetchFeedPayload,ParseFeedPayload,MatchFeedItems,PublishFeedRun,FinalizeFeedRun}` | `feed_items`, `feed_errors`, `merchant_products`, `offers`, `price_snapshots` | run detail page shows progress/metrics | `tests/Feature/Feeds/{FeedPipelineFixtures,FeedPublishingTest,FetchFeedPayloadTest,ParseFeedPayloadTest,FeedRunOutcomeTest}.php` | FUNCTIONAL |
| **Feed run error export** | `merchant.feeds.runs.errors.export` | `Feeds\Queries\FeedRunErrors` | `feed_errors` | CSV download | `tests/Feature/Feeds/FeedQueriesTest.php` | FUNCTIONAL |
| **Reconciliation (missing SKUs)** | (job pipeline) | `Feeds\Actions\ReconcileMissingListings` | `merchant_products`, `offers` | run detail page | `tests/Feature/Feeds/FeedPublishingTest.php` | FUNCTIONAL |
| **Matching engine (`matchItem`/intel.js Engine 2.0)** | (invoked from `MatchFeedItems` job, merchant/staff decision routes) | `Matching\Engine\ProductMatcher` | `matching_policies` | — (pure engine) | `tests/Unit/Matching/ProductMatcherTest.php`, `tests/Unit/Parity/{MatchingParityTest,MatchingSensitivityTest}.php`, `tests/Feature/Parity/DatabaseMatchingParityTest.php` | **PARITY VERIFIED** |
| **Merchant matching review (suggested/unmatched/history)** | `merchant.matching.{index,suggested,unmatched,history}` | `Matching\Queries\MerchantMatchingQueue` | `merchant_products`, `matching_decisions` | `resources/js/pages/merchant/matching/{index,suggested,unmatched,history}.tsx` | `tests/Feature/Matching/MatchingQueueTest.php`, `tests/Feature/Merchant/MerchantMatchingTest.php` | FUNCTIONAL |
| **Merchant match decision (confirm/choose/reject)** | `merchant.matching.listings.decision` | `Matching\Actions\DecideMatch` | `matching_decisions`, `merchant_products` | `resources/js/pages/merchant/matching/show.tsx` | `tests/Feature/Matching/DecideMatchTest.php` | FUNCTIONAL |
| **Merchant propose new product** | `merchant.matching.listings.propose` | `Matching\Actions\ProposeProductCandidate` | `product_candidates` | `resources/js/pages/merchant/matching/show.tsx` | `tests/Feature/Matching/MatchListingTest.php` | FUNCTIONAL |
| **Staff catalogue matching queue** | `admin.catalogue.matching.index` | `Matching\Queries\StaffMatchingQueue` | `merchant_products`, `matching_conflicts` | `resources/js/pages/admin/catalogue/matching/index.tsx` | `tests/Feature/Admin/CatalogueMatchingTest.php` | FUNCTIONAL |
| **Staff decision / rematch (relink published)** | `admin.catalogue.matching.listings.{decision,rematch}` | `Matching\Actions\{DecideMatch,Rematch}` | `matching_decisions` | `resources/js/pages/admin/catalogue/matching/show.tsx` | `tests/Feature/Matching/RematchHistoryTest.php`, `tests/Feature/Admin/CatalogueMatchingTest.php` | FUNCTIONAL |
| **Staff candidate / conflict resolution** | `admin.catalogue.matching.{candidates,conflicts}.resolve` | `Matching\Actions\{ResolveProductCandidate,ResolveConflict}` | `product_candidates`, `matching_conflicts` | `resources/js/pages/admin/catalogue/matching/show.tsx` | `tests/Feature/Admin/CatalogueMatchingTest.php` | FUNCTIONAL |
| **Audit log of privileged actions** | (cross-cutting) | `Platform\Audit\AuditLogger` | `audit_logs` | (no dedicated viewer page yet) | covered inline in the above Feature tests (assert `AuditLog` rows) | FUNCTIONAL |
| **Feature flags** | (cross-cutting, `feature:` middleware) | `Platform\Features\FeatureFlags` | `config/features.php` (no DB table) | shared Inertia prop | `tests/Unit/Platform/**`, route-level feature tests | FUNCTIONAL |
| **Merchant context switcher** | `merchant.context.update` | `ResolveMerchantContext` middleware | `merchant_user` | merchant portal header | `tests/Feature/Merchant/**` (isolation tests) | FUNCTIONAL |
| Compliance hold on matched listings | (part of `MatchFeedItems`) | `Matching\Actions\ComplianceHolds` | `merchant_products.match_status=compliance_hold` | matching review pages | `tests/Feature/Matching/MatchListingTest.php` | FUNCTIONAL |
| Price anomaly re-check after publish | (part of `PublishFeedRun`) | `Pricing\Actions\RecheckProductAnomalies` | `offers` (anomaly flags) | — | `tests/Feature/Feeds/FeedPublishingTest.php` (asserts anomaly flags) | FUNCTIONAL |

## Later phases (all NOT STARTED)

Purchase-link redirect / `/go` enforcement (Phase 5), review/community moderation, ranking-lab UI,
billing & subscriptions, affiliate reconciliation, live rooms, canonical-product creation from
candidates (Phase 8), API push feed transport. Do not mark any of these FUNCTIONAL until code exists.
