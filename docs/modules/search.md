# Module: Search

Namespace `App\Domain\Search`. ADR: 0016. Design: `docs/architecture/phase-3-search.md`.

## Responsibilities

- Full-text search and header suggestions across products, brands, categories, ingredients and merchants,
  for a visitor's market.
- Keep a search index (local database table or Meilisearch) eventually consistent with offers, prices,
  compliance and catalogue edits, via a transactional outbox.
- Re-verify every hit against the live database before it reaches a response (stale-index defence).
- Record search and click analytics without ever storing an IP address, user id or raw query/session id.
- Provide zero-result demand aggregates to the rest of the product (k-anonymised).

Owned tables: `search_documents` (local engine only), `search_synonyms`, `search_index_outbox`,
`search_queries`, `search_clicks`, `search_demand_daily`. Search never reads Commercial/Affiliate data
(`tests/Architecture/SearchBoundariesTest.php` enforces it) — organic order is relevance + public signals
only.

## Public services

| Service | Method | Notes |
|---|---|---|
| `SearchService` | `search(SearchQuery)`, `suggest(string $prefix, string $market, int $limit = 8)` | The one entry point HTTP controllers use; delegates to whichever `SearchEngine` is bound |
| `Contracts\SearchEngine` | `upsert`, `delete`, `search`, `suggest`, `swap`, `applySettings` | Port; two adapters below |
| `Engines\DatabaseSearchEngine` | — | Local/test: `search_documents` + `Local\LocalQueryEvaluator`, reproduces prototype relevance exactly |
| `Engines\MeilisearchSearchEngine` | — | Production only; Scout's Meilisearch client; held to a behavioural contract, not prototype ordering |
| `Queries\VisibleSearchHits` | `load(list<SearchHit\|SuggestItem> $hits, MarketContext, bool $withBrandProductCounts = false)` | **The single place that decides which hits may be shown** — re-checks compliance (`ComplianceResolver::decideMany`) and, for shops, an active shipping zone (A-28); drops anything no longer valid even if the index still lists it |
| `Analytics\RecordSearch` | `record(SearchRecordInput)` | One synchronous insert into `search_queries`; returns the search id or `null` (prefetch/too-short/failed) |
| `Analytics\RecordSearchClick` | `record(SearchClickInput)` | Attributes a click; see rules below |
| `Analytics\AggregateSearchDemand` | `run(DateTimeImmutable $day)` | Builds one UTC day of `search_demand_daily` |
| `Analytics\SearchDemandReport` | `topQueries(string $market, int $days, ...)` | k-anonymised read (≥ `min_demand_sessions`, summed per day) |
| `Analytics\PruneSearchAnalytics` (console) | — | Nulls `session_hash` after 90 days, deletes rows past 13 months |

## Engine selection (`SearchServiceProvider`)

Bound from `scout.driver`: `meilisearch` → `MeilisearchSearchEngine`; `collection`/`database`/`null`
(`LOCAL_DRIVERS`) → `DatabaseSearchEngine`; any other value, or a local driver when
`app()->environment('production')`, throws at resolution — the local engine does a full document scan per
query and is refused in production rather than silently degrading. Scout's `Searchable` trait is **not**
used on models (documents need per-market data a model save cannot compute).

## Index documents

Per active market, a product document carries `markets.{CC}.{compliance, purchasable, offer_count,
min_total_minor, min_total_market_minor, min_total_eur_minor, currency, in_stock}` plus
`{blocked,purchasable,offer,unknown}_markets[]`. A **blocked** market has no price data at all; **unknown**
keeps an informational total with `purchasable: false`. `min_total_market_minor` (market currency) is what
the price-range filter reads; `min_total_eur_minor` (converted at index time, ADR-0017) is what "lowest
total" sorting reads across markets. Displayed prices always come from the live cached comparison, never
the index. Brand/category/ingredient/merchant documents carry public names, slugs, counts and (merchants)
a public trust summary only. Coupons and articles are not indexed (A-25).

`ProductIndexSettings::VERSION` is **2**: the price filter moved from `min_total_minor` (offer currency,
now display-only, not filterable) to `min_total_market_minor` (market currency, filterable) — a
mixed-currency market cannot be range-filtered on an un-normalised amount. A version bump changes the
settings fingerprint and needs `comparo:search:sync-settings` + a full reindex.

## Indexing pipeline

`search_index_outbox (entity, entity_id, priority, queued_at)` is upserted by after-commit listeners
(`Indexing\Listeners\EnqueueProductDocuments` on `OfferPublished`/`OfferDeactivated`/`OfferRelinked`/
`PriceChanged`/`ComplianceRuleChanged` — the last one priority; `Indexing\CatalogIndexTriggers` for
catalogue/merchant edits, chunked). `Jobs\ProcessSearchOutbox` (queue `search`, 60s timeout, `retry_after`
90, `ShouldBeUniqueUntilProcessing`) reads ≤ `comparo.search.indexing.batch` (200) rows, re-checks current
state, writes one bulk upsert/delete, and deletes a row **only if it still carries the exact `queued_at`
that was read** — a row that changed again mid-run keeps a newer `queued_at` and survives for the next
run. Products indexed in units of `comparo.search.indexing.product_batch` (25). Stops taking new work
after `WORK_SECONDS` (40s) and re-dispatches itself; scheduled every minute, priority rows dispatch
immediately.

A full rebuild (`comparo:search:reindex {entity?}`) builds `<index>_tmp` and swaps atomically, using
`Indexing\RebuildMarker` to dual-write and record ids touched mid-rebuild so nothing built stale survives
the swap. **Country activation is a separate, cheaper path** (`Jobs\QueueFullSearchReindex`, dispatched
from `CatalogIndexTriggers::requestFullReindex`): it syncs settings and fans the outbox out across every
document source in chunks — it does **not** run the `<index>_tmp` swap (too slow for the search queue's
60s budget) and does not drop orphaned documents; an operator runs the swap command by hand for that.

## Analytics privacy

| Field | How |
|---|---|
| `query_normalized` | `Analytics\QueryRedactor`: folded, ≤ 100 chars; emails → `[email]`, phone-shaped groups → `[phone]`, bare 7+ digit runs → `[number]` except an 8/12/13/14-digit run passing the GS1 check digit (a pasted EAN/GTIN, not personal data) |
| `session_hash` | `Analytics\SessionHasher`: HMAC-SHA256(session id, a secret itself derived per UTC day from `APP_KEY`) — same session hashes differently every day; raw session id never stored |
| Click attribution | `search.clicks` requires the search row to exist, be ≤ `click_window_minutes` (30) old, have listed the entity at that position, **and** the click's recomputed session hash to match the search's stored one; first accepted click per (search, entity) only; endpoint always answers 204 |
| Demand k-threshold | `SearchDemandReport::topQueries` exposes a query only when **summed** daily `sessions` ≥ `min_demand_sessions` (3) over the window — summed, not deduplicated, because the daily rotation makes a session hash meaningless across days; flagged for DPO sign-off (F-16), not settled policy |
| Retention | `raw_retention_months` 13, `session_hash_days` 90 — both live in `config/comparo.php` (`comparo.search.analytics.*`), read by `AnalyticsSettings::fromConfig()` |

Never stored: IP address, user id, user agent, raw query text, raw session id.

## Routes and limiters

`GET /search` (`throttle:search-page`, 60/min/IP, `noindex,follow`), `GET
/api/public/v1/search/suggest` (`throttle:search-suggest`, 120/min/IP, 2–64 chars, 60s cache), `POST
/search/clicks` (`throttle:search-clicks`, 60/min/IP). All three limiters and the engine binding live in
`SearchServiceProvider`.

## Parity status

| Capability | Engine | Status |
|---|---|---|
| Relevance ordering, fuzzy scoring, synonym expansion, facet counts | `DatabaseSearchEngine` (local/test) | **PARITY VERIFIED** against `tests/Fixtures/PrototypeParity/search.json` |
| Relevance ordering | `MeilisearchSearchEngine` (production) | Not prototype-ordering; held to a shared behavioural contract suite instead (navigational queries, EAN/SKU exact match, blocked-product exclusion, zero-result handling) — see `docs/integrations/matrix.md` for its untested-against-a-live-server caveat |
| Indexing (outbox, rebuild, settings sync) | both | FUNCTIONAL |
| Analytics (recording, redaction, click attribution, demand aggregation) | — | FUNCTIONAL |

Deliberate deviations from the prototype (documented, tested): blocked products removed per market after
scoring (A-21); coupons/articles not searched (A-25); the prototype's trailing-space inconsistency is
normalised (query trimmed once). See ADR-0016 for the full rationale and rejected alternatives.
