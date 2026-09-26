# Phase 3 design — search & discovery

Status: **implemented** (2026-09-26; binding design accepted 2026-09-25). See ADR-0016, ADR-0017 and
`docs/modules/search.md` / `docs/modules/pricing.md` for the as-built module docs; this file remains the
design record and is corrected below where the implementation took a different, deliberate path.
Synthesised from the prototype search analysis
(`searchAll` / `fuzzyScore` / `H.synonyms`) and the Laravel architecture analysis. The prototype is the
behaviour spec where it is deterministic; invariant 2 (compliance before serialization) overrides it where
the prototype lists blocked products with prices (DC 15796, 15832 claim an exclusion that the code does
not implement).

**Exit flow:** a visitor searches → results for their market (blocked products absent, unknown products
without purchase data) with type tabs, facets, filters, sorting and pagination → a click is attributed →
the header suggest box answers from the same engine → offer, price and compliance changes reach the index
through queued, idempotent updates → zero-result demand is recorded privately and aggregated for Growth OS.

## 1. Module boundaries (`App\Domain\Search`)

| Layer | Contents | Rules |
|---|---|---|
| Pure | `Query\{SearchQuery, SearchFilters, SortOption}`, `Relevance\{PrototypeRelevance, Levenshtein, SynonymExpansion}` (port of `fuzzyScore`, `lev`, `H.synonyms` expansion, entity offsets, stable tie order), `DidYouMean`, `Facets\FacetShaper`, `Local\LocalQueryEvaluator` (relevance + typed filters + facet counts + deterministic ties) | no DB, no clock, no config reads; exact parity with `search.json` |
| Documents | `Documents\{Product,Brand,Category,Ingredient,Merchant}Document` DTOs + `*DocumentBuilder` | builders are the only document factories; never commercial/private fields |
| Queries | `Queries\ProductMarketSnapshot` (runs the existing offer comparison per **active** market) | only data loader for documents |
| Engine port | `Contracts\SearchEngine` (`upsert`, `delete`, `search`, `suggest`, `swap`, `applySettings`) with typed filters (never raw strings) | adapters chosen by `scout.driver` |
| Adapters | `Engines\DatabaseSearchEngine` (`search_documents` table + `LocalQueryEvaluator`; local/testing), `Engines\MeilisearchSearchEngine` (Scout's Meilisearch client; production) | Meilisearch keys never reach browsers |
| Indexing | `Indexing\{SearchOutbox, SearchOutboxProcessor, RebuildMarker, listeners}`, the queued job itself is `Jobs\ProcessSearchOutbox` (its own `Jobs` namespace, alongside `Jobs\QueueFullSearchReindex`/`SyncSearchSettings`), `Settings\ProductIndexSettings` (versioned), commands `comparo:search:sync-settings`, `comparo:search:reindex` | queued on `search`, idempotent |
| Analytics | `Analytics\{RecordSearch, RecordSearchClick, QueryRedactor, AggregateSearchDemand}` | no IP, no user id |
| HTTP | `Controllers\Search\*`, `Presenters\SearchPresenter` (re-checks compliance for every hit) | thin |

Scout's `Searchable` trait is **not** used on models: prices and compliance change through offers and
rules, not product saves, and documents need per-market data a model should not compute. Scout remains the
source of configuration and of the Meilisearch client. Search never reads Commercial/Affiliate data; organic
order is relevance + public signals only (sponsored placements are Phase 13, separate and labelled).

## 2. Index documents

**Product** (only `Product::listed()`; merged/retired products are deleted from the index):
`id, slug, name, pack_label, short_description (≤ 300), variant_names[], identifiers[] (product + variant
EANs, SKUs), brand{slug,name}, brand_aliases[] (non-rejected), category{slug,name,path[]},
ingredient_names[], ingredients[] (slugs), rating{average,count}` and a per-market map for **active**
markets only:
`markets.{CC}: {compliance: allowed|restricted|unknown|blocked, purchasable, offer_count, min_total_minor,
currency, min_total_eur_minor, in_stock}` plus `blocked_markets[]`, `purchasable_markets[]`,
`offer_markets[]`, `unknown_markets[]`, `indexed_at`, `schema_version`.
A blocked market carries no price data at all. `unknown` keeps the informational total with
`purchasable: false` (the product page shows unknown prices without purchase links). Sorting across
currencies uses `min_total_eur_minor` (comparison currency, converted at index time). Displayed prices come
from the live cached comparison, never from the index.

Brand, category, ingredient and merchant documents carry public names, slugs, counts and (merchants)
public trust summary only. Coupons and articles are not indexed in Phase 3 (A-25).

## 3. Relevance and parity

- **Local/test engine** (`DatabaseSearchEngine`) reproduces the prototype exactly: `fold` normalisation
  (same as `TextFold::fold`), minimum 2 characters after trim, `fuzzyScore` (100 exact, 92 prefix, 80
  contains, else token hits ×60 + Levenshtein-fuzzy ×38 over the query token count), per-entity offsets
  (brand +2, shop +1 / web −10, category −6, ingredient −12, product fields brand+name −4, ingredients −22,
  category −30, SKU/EAN exact → 100), `H.synonyms` substring expansion (5 groups, floor score 34 for
  products below 40), stable tie order (products, brands, shops, categories, ingredients). Proven by
  `tests/Fixtures/PrototypeParity/search.json` (exporter section `search`). `search_documents.payload`
  (the full document, JSON) is what `Local\LocalQueryEvaluator` actually scores against; its sibling column
  `searchable_text` (the folded concatenation) is stored only for manual inspection of a row — it is
  **not** read by the query path and is not a SQL prefilter ahead of the in-PHP scorer.
- **Deliberate deviations** (documented, tested): blocked products are removed per market after scoring
  (A-21); coupons and articles are not searched (A-25); the prototype's trailing-space inconsistency between
  products and other types is normalised (query is trimmed once).
- **Production engine** (Meilisearch) is not held to prototype ordering (bucket ranking rules, token-level
  synonyms and typos). It is held to behavioural contract tests shared with the database engine:
  navigational queries return the right top hit, EAN/SKU go straight to the product, `kreatin` finds
  creatine, blocked products are absent in their market, nonsense returns zero results. The contract suite
  runs against Meilisearch only when `MEILISEARCH_HOST` is reachable.
- Settings from code (`ProductIndexSettings::VERSION`, now **2**): searchable order name, brand, aliases,
  ingredient names, category, variants, identifiers; filterable `*_markets`, `markets.*.{compliance,
  in_stock, min_total_market_minor, min_total_eur_minor}`, `brand.slug`, `category.path`, `ingredients`,
  `rating.average`; sortable `markets.*.min_total_eur_minor`, `rating.average`, `rating.count`, `name`;
  typo tolerance `{oneTypo: 3, twoTypos: 6}` (prototype `lev ≤ 1/2`), typos off for `identifiers`;
  synonyms from the `search_synonyms` table seeded with `H.synonyms`. Version 2 corrects the price filter:
  it reads `markets.{CC}.min_total_market_minor` (the market-currency amount, filterable), not
  `min_total_minor` (the offer's own currency, display only — **not** filterable; a mixed-currency market
  cannot be range-filtered on an un-normalised amount). `MerchantIndexSettings.FILTERABLE` similarly
  exposes `shipping_markets` (not the offer-level fields).

## 4. Routes and UX

- `GET /search` (name `search`, `MarketContext`, `robots: noindex,follow`, canonical `/search`),
  `GET /api/public/v1/search/suggest?q=&market=` (2–64 chars, limiter `search-suggest` 120/min per IP,
  60 s cache per market + normalised prefix, no prices, no outbound links, blocked excluded) and
  `POST /search/clicks` (throttled, validated against the recorded search). Locale/market URL prefixes
  arrive for **all** public routes together in Phase 11 (A-20).
- Search page: type tabs with counts (all, products, brands, shops, categories, ingredients), filters
  (brand, category, ingredient, price range in the market currency, in stock, rating), sorting (relevance,
  lowest landed total, rating, name), pagination, did-you-mean + "browse categories" on zero results,
  per-viewer recent searches kept in `localStorage` only (never sent to the server). Did-you-mean is a
  `SearchService`/presenter concern (`SearchPresenter` calls `Navigation::didYouMean` from
  `$results->didYouMean` only when the hit count is zero), not something each engine computes itself: the
  local engine's `SearchResults::didYouMean` is populated by `Local\LocalQueryEvaluator`
  (A-28: it runs on the visible entries), while `Engines\MeilisearchSearchEngine` always returns an empty
  `didYouMean` — Meilisearch's own typo tolerance already broadens the query, so a zero-result Meilisearch
  search falls straight through to "browse categories" instead.
- Header search box with debounced suggestions (keyboard navigable combobox, aria-live counts).

## 5. Indexing

A transactional outbox `search_index_outbox (entity, entity_id, priority, queued_at)` is upserted by
after-commit listeners (idempotent, merges repeated changes). `Jobs\ProcessSearchOutbox` (queue `search`,
timeout 60 s < `retry_after` 90, `ShouldBeUniqueUntilProcessing`) reads current state + compliance when it
runs, sends one bulk upsert/delete for ≤ `comparo.search.indexing.batch` (200) rows, and deletes a
processed row **only while it still carries the exact `queued_at` that was read** for it — a row
re-enqueued (because the entity changed again) during its own indexing keeps a later `queued_at` and
survives the delete, so it is picked up by the next run instead of being lost to the race. Products are
processed in smaller units of `comparo.search.indexing.product_batch` (25, since each product runs one
offer comparison per active market). The job stops starting new units of work after `WORK_SECONDS` (40s,
below the 60s timeout) and re-dispatches itself while rows remain, so a slow batch ends itself cleanly
instead of being killed mid-write. Scheduled every minute; priority rows (compliance changes) also
dispatch a `priorityOnly` copy immediately after commit. While a full rebuild is active for an index
(`Indexing\RebuildMarker`), the same processor also dual-writes to the rebuild's `<index>_tmp` twin and
records the entity ids it touched, so the post-swap re-enqueue below never misses a change that landed
mid-rebuild.

| Trigger | Re-indexes |
|---|---|
| `OfferPublished`, `OfferDeactivated`, `PriceChanged` | the product |
| `OfferRelinked` | previous and new product |
| `ComplianceRuleChanged` (new event raised after commit from the existing hook) | the product, priority |
| coupon, shipping zone, trust signal, risk event, merchant status changes | merchant doc + its products (chunked) |
| product / brand / category / ingredient edits | that document (+ products for brand/category renames) |
| country activation (`is_active` change, or deleting an active country) | `Jobs\QueueFullSearchReindex` (`CatalogIndexTriggers::requestFullReindex`). This is **not** the `<index>_tmp` swap below: it dispatches `SyncSearchSettings` (new per-market filterable/sortable attributes change the settings fingerprint, A-22; the Meilisearch settings task can take long, so it runs as its own job) and enqueues every document source into the outbox in chunks — `ProcessSearchOutbox` then rewrites all documents with the new market data in bounded batches. Creating a country does **not** trigger this (countries are created inactive); an already-active new country needs an operator to run `comparo:search:sync-settings` and `comparo:search:reindex` by hand |

Full reindex: `comparo:search:reindex {entity?}` (the swap command, distinct from `QueueFullSearchReindex`
above) builds `<index>_tmp` in batches and swaps atomically, using `RebuildMarker` (above) so changes
indexed during the build are not lost at the swap; unlike the country-activation path it also drops
orphaned documents.

## 6. Analytics (privacy by design)

`search_queries` (ULID `search_id`, `occurred_at`, market, locale, source page|suggest,
`query_normalized` ≤ 100 chars folded with emails/phones/long digit runs redacted (GTIN check-digit runs of
8/12/13/14 digits are kept — `Analytics\QueryRedactor`), `query_hash`, allow-listed filters, `result_count`,
`result_refs` (first-page ids), `session_hash` (HMAC-SHA256, daily-rotating secret, nulled after 90 days —
`Analytics\SessionHasher`), `is_bot`). No IP, no user id. The row itself is written by `RecordSearch` with
**one synchronous insert** in the request that ran the search, specifically so a result click arriving
right after the page load can always be attributed regardless of queue lag; only the secondary
`SearchPerformed` event (queued on `analytics`; prefetch requests skipped) and, when `result_count = 0`,
`ZeroResultSearchRecorded` wait for a worker — both are dispatched with no consumer registered today
(reserved for future use, not a gap, see `docs/architecture/event-matrix.md`). `SearchResultClicked` via
`POST /search/clicks` (`Analytics\RecordSearchClick`) requires the search row to already exist, be at most
30 minutes old, have listed the clicked entity at that exact position, **and** the click's session hash —
recomputed for the search's own UTC day — to match the search's stored `session_hash`; the first accepted
click per (search, entity) is stored, repeats and mismatches are silently ignored, and the endpoint always
answers 204 either way. `search_demand_daily` (date, market, query hash, normalised query, searches, zero
results, clicks, sessions) is built per UTC day from non-bot, first-page rows; `SearchDemandReport::topQueries`
exposes a query only when the **sum** of its daily `sessions` values over the requested window is ≥ 3
(`comparo.search.analytics.min_demand_sessions`) — because `session_hash` rotates daily, this sums
distinct-per-day sessions rather than deduplicating a returning visitor across days (deliberate: linking
days is exactly what the rotation prevents), which is flagged for DPO sign-off (F-16) rather than presented
as settled policy. Raw rows kept 13 months (D-09 default, `comparo.search.analytics.raw_retention_months`),
`comparo:search:prune-analytics` (daily 00:50 UTC).

## 7. Latent bug fixed in this phase

`ProductOfferComparison::lowestTotal` compares `Money` values of different currencies and would throw when a
market has offers in two currencies. The per-market snapshot needs a defined answer: the lowest total is
chosen by the comparison-currency amount (bcmath conversion, `ExchangeRates`), reported in the offer's own
currency. Existing parity (single-currency markets) must stay byte-identical.

## 8. Open decisions (safe defaults applied — `docs/autonomy/OPEN-DECISIONS.md` A-20…A-27)

Hiding blocked products instead of "not available in your market" (A-21, Compliance Lead); D-24 default
market for crawlers (DE); Meilisearch hosting + keys (CREDENTIAL_REQUIRED); search-log privacy sign-off
(DPO, D-09); `parseNL` intent labels and the Ask feature deferred (A-27).
