# Phase 3 design — search & discovery

Status: **binding for Phase 3** (2026-09-25). Synthesised from the prototype search analysis
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
| Indexing | `Indexing\{SearchOutbox, ProcessSearchOutbox job, listeners}`, `Settings\ProductIndexSettings` (versioned), commands `comparo:search:sync-settings`, `comparo:search:reindex` | queued on `search`, idempotent |
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
  `tests/Fixtures/PrototypeParity/search.json` (exporter section `search`).
- **Deliberate deviations** (documented, tested): blocked products are removed per market after scoring
  (A-21); coupons and articles are not searched (A-25); the prototype's trailing-space inconsistency between
  products and other types is normalised (query is trimmed once).
- **Production engine** (Meilisearch) is not held to prototype ordering (bucket ranking rules, token-level
  synonyms and typos). It is held to behavioural contract tests shared with the database engine:
  navigational queries return the right top hit, EAN/SKU go straight to the product, `kreatin` finds
  creatine, blocked products are absent in their market, nonsense returns zero results. The contract suite
  runs against Meilisearch only when `MEILISEARCH_HOST` is reachable.
- Settings from code (`ProductIndexSettings::VERSION`): searchable order name, brand, aliases, ingredient
  names, category, variants, identifiers; filterable `*_markets`, `markets.*.{compliance,in_stock,
  min_total_eur_minor}`, `brand.slug`, `category.path`, `ingredients`, `rating.average`; sortable
  `markets.*.min_total_eur_minor`, `rating.average`, `rating.count`, `name`; typo tolerance
  `{oneTypo: 3, twoTypos: 6}` (prototype `lev ≤ 1/2`), typos off for `identifiers`; synonyms from the
  `search_synonyms` table seeded with `H.synonyms`.

## 4. Routes and UX

- `GET /search` (name `search`, `MarketContext`, `robots: noindex,follow`, canonical `/search`),
  `GET /api/public/v1/search/suggest?q=&market=` (2–64 chars, limiter `search-suggest` 120/min per IP,
  60 s cache per market + normalised prefix, no prices, no outbound links, blocked excluded) and
  `POST /search/clicks` (throttled, validated against the recorded search). Locale/market URL prefixes
  arrive for **all** public routes together in Phase 11 (A-20).
- Search page: type tabs with counts (all, products, brands, shops, categories, ingredients), filters
  (brand, category, ingredient, price range in the market currency, in stock, rating), sorting (relevance,
  lowest landed total, rating, name), pagination, did-you-mean + "browse categories" on zero results,
  per-viewer recent searches kept in `localStorage` only (never sent to the server).
- Header search box with debounced suggestions (keyboard navigable combobox, aria-live counts).

## 5. Indexing

A transactional outbox `search_index_outbox (entity, entity_id, priority, queued_at)` is upserted by
after-commit listeners (idempotent, merges repeated changes). `ProcessSearchOutbox` (queue `search`,
timeout 60 s < `retry_after` 90, `ShouldBeUniqueUntilProcessing`) reads current state + compliance when it
runs, sends one bulk upsert/delete for ≤ 200 rows and deletes processed rows with `queued_at ≤ snapshot`.
Scheduled every minute; priority rows dispatch immediately.

| Trigger | Re-indexes |
|---|---|
| `OfferPublished`, `OfferDeactivated`, `PriceChanged` | the product |
| `OfferRelinked` | previous and new product |
| `ComplianceRuleChanged` (new event raised after commit from the existing hook) | the product, priority |
| coupon, shipping zone, trust signal, risk event, merchant status changes | merchant doc + its products (chunked) |
| product / brand / category / ingredient edits | that document (+ products for brand/category renames) |
| country activation | full reindex + settings sync |

Full reindex: `comparo:search:reindex {entity?}` builds `<index>_tmp` in batches and swaps atomically.

## 6. Analytics (privacy by design)

`search_queries` (ULID `search_id`, `occurred_at`, market, locale, source page|suggest,
`query_normalized` ≤ 100 chars folded with emails/phones/long digit runs redacted, `query_hash`, allow-listed
filters, `result_count`, `result_refs` (first-page ids), `session_hash` (salted HMAC rotating daily, nulled
after 90 days), `is_bot`). No IP, no user id. `SearchPerformed` is recorded server-side when the query runs
(queued on `analytics`; prefetch requests skipped); `ZeroResultSearchRecorded` when `result_count = 0`;
`SearchResultClicked` via `POST /search/clicks` only for a recent (≤ 30 min) search whose `result_refs`
contain the entity at that position, deduplicated per (search, entity). `search_demand_daily` (date, market,
query hash, normalised query, searches, zero results, clicks) is built from non-bot rows and exposes a query
only when ≥ 3 distinct sessions made it. Raw rows 13 months (D-09 default), `comparo:search:prune-analytics`.

## 7. Latent bug fixed in this phase

`ProductOfferComparison::lowestTotal` compares `Money` values of different currencies and would throw when a
market has offers in two currencies. The per-market snapshot needs a defined answer: the lowest total is
chosen by the comparison-currency amount (bcmath conversion, `ExchangeRates`), reported in the offer's own
currency. Existing parity (single-currency markets) must stay byte-identical.

## 8. Open decisions (safe defaults applied — `docs/autonomy/OPEN-DECISIONS.md` A-20…A-27)

Hiding blocked products instead of "not available in your market" (A-21, Compliance Lead); D-24 default
market for crawlers (DE); Meilisearch hosting + keys (CREDENTIAL_REQUIRED); search-log privacy sign-off
(DPO, D-09); `parseNL` intent labels and the Ask feature deferred (A-27).
