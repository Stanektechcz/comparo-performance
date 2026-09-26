# ADR-0016: Search and discovery

- Status: Accepted
- Date: 2026-09-26
- Related: A-20…A-29, D-09, D-24, `docs/architecture/phase-3-search.md`, `docs/modules/search.md`;
  ADR-0007 (compliance before serialization), ADR-0009 (money as integer minor units), ADR-0015 (domain
  events)

## Context

Phase 3 adds a public search page (`/search`), header suggestions and result-click attribution on top of
the existing catalogue, offers, compliance and pricing domains. Three decisions could not be deferred:
which engine runs search in which environment, how a stale search index is kept from ever showing a
blocked or already-changed offer, and how search behaviour is measured without building a tracking
system.

## Decision

### Engine port with two adapters, selected by `scout.driver`

`App\Domain\Search\Contracts\SearchEngine` (`upsert`, `delete`, `search`, `suggest`, `swap`,
`applySettings`) is bound in `SearchServiceProvider` from `scout.driver`:

- `meilisearch` → `Engines\MeilisearchSearchEngine`, Scout's Meilisearch client. The only driver allowed
  in the `production` environment.
- `collection` / `database` / `null` (`SearchServiceProvider::LOCAL_DRIVERS`) → `Engines\DatabaseSearchEngine`
  against the `search_documents` table, reproducing prototype relevance exactly
  (`Relevance\PrototypeRelevance`, proven against `tests/Fixtures/PrototypeParity/search.json`).
- Any other driver value, or a local driver in production, throws at resolution time
  (`InvalidArgumentException` / `RuntimeException` in `SearchServiceProvider::register`) instead of
  silently searching nothing — the local engine does a full document scan per query, which only suits
  the demo catalogue and tests.

Scout's `Searchable` trait is **not** used on models: prices and compliance change through offers and
compliance rules, not product saves, and index documents need per-market data a model should not compute.
`Search\SearchService` is the one entry point HTTP controllers call; it never exposes which adapter is
bound.

### Public-only documents, with a per-market map

Index documents (`Documents\{Product,Brand,Category,Ingredient,Merchant}Document`, built only by their
`*DocumentBuilder`) carry public names, slugs, counts and, for products, a per-**active**-market map:
`markets.{CC}.{compliance, purchasable, offer_count, min_total_minor, min_total_market_minor,
min_total_eur_minor, currency, in_stock}` plus `blocked_markets[]` / `purchasable_markets[]` /
`offer_markets[]` / `unknown_markets[]`. A **blocked** market carries no price data at all (invariant 2);
**unknown** keeps the informational total with `purchasable: false`. `min_total_market_minor` (the
market-currency amount) is filterable for the price-range filter; `min_total_eur_minor` (converted at
index time, ADR-0017) is what sorting by lowest total uses, since EUR is the one comparison currency every
active market has a rate into. Displayed prices always come from the live cached comparison, never from
the index — the index only orders and filters. Commercial fields (commission, plan, campaign spend) are
never read by any document builder (same boundary as ComparoRank, ADR-0004); `tests/Architecture/SearchBoundariesTest.php`
enforces it.

### Stale-index defence: the presenter re-checks, always

An index can be behind the database for the seconds between a write and its outbox-driven re-index. Every
hit — search results and header suggestions alike — is re-verified before it reaches an HTTP response by
`Queries\VisibleSearchHits`: products are re-decided against `ComplianceResolver::decideMany` for the
requesting market (a now-blocked product is dropped even if the index still lists it as purchasable),
shops must still have an active shipping zone in the market (A-28), and brands/categories/ingredients must
still exist. This is the single place that decides which hits may be shown; presenters only format its
result. The index is therefore an optimisation for *finding* candidates, never the authority for
*showing* them — invariant 2 holds even against a lagging index.

### Indexing: transactional outbox + time-budgeted worker + rebuild marker

`search_index_outbox (entity, entity_id, priority, queued_at)` is upserted by after-commit domain-event
listeners (`Indexing\Listeners\EnqueueProductDocuments` on `OfferPublished`, `OfferDeactivated`,
`OfferRelinked`, `PriceChanged` and, with `priority`, `Compliance\Events\ComplianceRuleChanged`), plus
chunked fan-out for merchant, brand, category, ingredient and market changes
(`comparo.search.indexing.fan_out_chunk`, default 500). `Jobs\ProcessSearchOutbox` (queue `search`, 60 s
timeout under a 90 s `retry_after`, `ShouldBeUniqueUntilProcessing`) reads up to
`comparo.search.indexing.batch` (200) rows ordered by `queued_at`, re-reads current state and compliance
at run time, sends one bulk upsert/delete per entity type and **only deletes a row that still carries the
`queued_at` it read** — a row re-enqueued mid-run (because it changed again while being indexed) keeps its
later `queued_at` and survives for the next run, so a change is never lost to a race with its own
indexing. It stops taking new units of work after `WORK_SECONDS` (40s) and re-dispatches itself while rows
remain; scheduled every minute, with priority rows also dispatched immediately after commit so a
compliance block leaves results without waiting for the backlog.

A full rebuild (`comparo:search:reindex {entity?}`) builds a `<index>_tmp` twin and swaps atomically.
`Indexing\RebuildMarker` closes the race a naive dual-write-then-swap would have: while a rebuild marker
is active for an index, every live write also goes to the twin **and** records its entity ids
(cache-backed, TTL `comparo.search.indexing.rebuild_marker_ttl_seconds`, default 6h, so an unclean rebuild
stops dual-writing eventually). Right after the swap, `finish()` returns every id recorded during the
rebuild — a chunk built *before* a change but written *after* its dual write is otherwise stale — and the
reindexer re-enqueues exactly those ids. Country activation (`Jobs\QueueFullSearchReindex`) is a
*different, cheaper* path than this swap rebuild: it dispatches a settings sync (new per-market filterable
attributes change the settings fingerprint, A-22) and enqueues every document source into the outbox in
chunks, letting `ProcessSearchOutbox` rewrite documents with the new market data in bounded batches —
deliberately not the `<index>_tmp` swap, which the 60s search-queue budget cannot fit for the whole
catalogue. An operator still runs the swap command by hand to drop orphaned documents or for an
already-active new country.

### Analytics: privacy by construction, not by policy

`Analytics\RecordSearch` writes one `search_queries` row **synchronously** in the request that performed
the search (queued dispatch of `SearchPerformed`/`ZeroResultSearchRecorded` follows, for future
consumers only — see `docs/architecture/event-matrix.md`). The row never contains an IP address, a user
id, a user agent or the raw query text:

- `query_normalized` is the output of `Analytics\QueryRedactor` — folded, whitespace-collapsed, capped at
  100 characters, with anything shaped like an email (`[email]`), a phone-like grouped number (`[phone]`),
  or a bare run of 7+ digits (`[number]`) redacted, **except** a run of exactly 8, 12, 13 or 14 digits that
  passes the GS1 mod-10 check digit (`Shared\Identifiers\Gtin::isValid`) — an EAN/UPC/GTIN a visitor pasted
  to find a product, kept verbatim as it is not personal data;
- `session_hash` is `Analytics\SessionHasher`: HMAC-SHA256(session id, a secret itself derived as
  HMAC-SHA256("comparo:search-session:" + UTC date, `APP_KEY`)), so the same browser session hashes
  differently on different UTC days and the raw session id is never stored; nulled after
  `comparo.search.analytics.session_hash_days` (90);
- raw rows are kept `comparo.search.analytics.raw_retention_months` (13) then deleted with their clicks
  (`comparo:search:prune-analytics`, daily 00:50 UTC, config-driven — see `docs/privacy/data-retention.md`).

A result click (`POST /search/clicks` → `Analytics\RecordSearchClick`) is accepted only for a search at
most `comparo.search.analytics.click_window_minutes` (30) old, from the same browser session (its hash
recomputed for the search's own UTC day must match — the click endpoint is called soon enough after the
search that "the search's day" and "today" are effectively the same, but the recomputation is always
against the search's day, not today's), that listed exactly that entity at that position; the first
accepted click per (search, entity) is stored, repeats are silently ignored, and the endpoint always
answers 204 regardless of outcome so it cannot be used to probe what was recorded.

`search_demand_daily` (date, market, query hash) is built by `Analytics\AggregateSearchDemand` from
non-bot, first-page (not `page=`) search rows for one UTC day; `Analytics\SearchDemandReport::topQueries`
exposes a query only when the **summed** `sessions` column across the requested window is
≥ `comparo.search.analytics.min_demand_sessions` (3, k-anonymity, A-24). Because `session_hash` rotates
daily, summing is deliberate — one browser searching on three different days is 3 sessions, not 1, which
is the price of the rotation the retention decision (D-09) wants; `docs/architecture/phase-3-search.md`
§6 flags this for DPO sign-off (F-16) rather than presenting it as already-approved policy.

### Routes and limiters

`GET /search` (name `search`, `noindex,follow`, `throttle:search-page` 60/min/IP),
`GET /api/public/v1/search/suggest` (`throttle:search-suggest` 120/min/IP, 2–64 char query, 60s cache per
market + normalised prefix) and `POST /search/clicks` (`throttle:search-clicks` 60/min/IP). All three
limiters are registered in `SearchServiceProvider::boot`, not `RouteServiceProvider`, alongside the
engine binding they gate.

## Consequences

- The local database engine is a full per-query scan; it is refused in production by a hard runtime
  check, not by convention, so a misconfigured `SCOUT_DRIVER` fails loudly at boot rather than degrading
  silently.
- Because the presenter always re-checks compliance and market visibility, a slow or backlogged outbox
  degrades *freshness of ranking/filters*, never *correctness of what is shown* — a deliberately
  asymmetric trade-off.
- The outbox's delete-only-if-`queued_at`-still-matches rule means a row that changes twice in one
  indexing window costs two runs, not one; acceptable given the 1-minute schedule and priority fast path.
- Session-hash rotation makes long-window demand deliberately over-count returning visitors; the correct
  fix (unique visitor counting without cross-day linkage) needs an explicit DPO decision, not an
  engineering one, and is called out rather than silently "fixed" in aggregation code.

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| One Meilisearch index per market (27 indexes) | A-22: multiplies settings-sync and reindex cost for no benefit — a per-market map inside one document does the same filtering |
| Add `Searchable` to `Product`/`Brand`/etc. and let Scout auto-sync on save | Prices and compliance change through offers/rules, not product saves; per-market data a model save cannot compute; the outbox's batching and priority path would not exist |
| Trust the index for compliance/visibility (no re-check) | Directly violates invariant 2; an indexing delay would show a blocked product's price for however long the outbox lags |
| Store raw session id, hash only at read time | Defeats the purpose — the raw id would then exist in the database, linkable across days, exactly what A-24 forbids |
| Count distinct session hashes across the whole demand window instead of summing per day | Would silently re-identify a returning visitor across days by matching their otherwise-unrelated daily hashes — the rotation exists to prevent that; summing keeps each day's hash meaningless in isolation |
