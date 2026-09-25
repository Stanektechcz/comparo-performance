# ADR-0013: Merchant feed ingestion pipeline

- Status: Accepted (amends ADR-0003)
- Date: 2026-09-25
- Related: A-09, A-11, D-25, `docs/architecture/phase-2-feeds-matching.md` §2–§8; ADR-0003, ADR-0012,
  [modules/feeds.md](../modules/feeds.md)

## Context

The prototype's feed import is a client-side simulation: pasted text, parsed in the browser, capped at
25 rows, never persisted (deviation #1). Phase 2 replaces it with a real, queued, auditable pipeline
that a merchant can point at a live URL or upload, and that reconciles what disappears from a feed
without ever deleting data. ADR-0003 established append-only `price_snapshots` written by a listener;
running the pipeline against `PublishOffer`'s actual transaction boundary showed that a listener (which
runs after commit) cannot guarantee the snapshot never diverges from the row it describes, so this ADR
amends that part of ADR-0003.

## Decision

### Sources, transports, formats

`feed_sources` (draft|active|paused|error|disabled — `FeedSourceStatus`) is keyed to one merchant and
one `FeedTransport` (`url`, `upload`, `api_push` not yet implemented, `manual_upload` for staff) and one
`FeedFormat` (`xml`|`csv`|`json`), with an immutable-content, versioned `feed_mappings` row per active
field mapping (single `is_current` per source, partial unique index).

### State machines

- **FeedSource:** `draft → active` on first successful run, `active ↔ paused` (merchant-controlled),
  `active → error` after 3 consecutive failures or an `AUTH_FAILED`/`BLOCKED_DESTINATION` row-fatal
  error, `error → active` on the next successful run, `any → disabled` (staff only), `disabled →
  draft` (staff only). Only `active` is scheduled (`isSchedulable()`); manual runs are allowed from
  `draft`, `active`, `error` (`allowsManualRun()`).
- **FeedRun:** `queued → fetching → parsing → normalizing → matching → publishing → completed`;
  `fetching → completed` with outcome `unchanged` on an identical payload checksum; any non-terminal
  status → `failed`; `queued`…`matching → cancelled`; **publishing is not cancellable**
  (`isCancellable()` excludes it — part of the run may already be visible). Transitions are
  compare-and-swap (`UPDATE … WHERE id = ? AND status = ?`, `Feeds\Lifecycle\FeedRunTransitions`); a
  job that loses the race exits quietly instead of erroring. At most one non-terminal run per source
  (`feed_runs_single_active` partial unique index).

### Jobs, queues, timeouts

Queue `feed-import`: `FetchFeedPayload` → `ParseFeedPayload` → `NormaliseFeedItems` → `MatchFeedItems`
→ `PublishFeedRun` → `FinalizeFeedRun` (`App\Domain\Feeds\Jobs`), each declaring `tries`, `timeout`,
`backoff()` and a failure transition to `failed`; ids only travel in job payloads, the correlation id is
restored into `Context` at the start of each job. Stage timeouts (`config('comparo.feeds.timeouts')`):
fetching, parsing, normalizing, matching, publishing each capped (matching and publishing at 60s by
default); `retry_after` on the queue connection must exceed the longest job timeout, and Horizon
supervisors are configured per queue so a long-running fetch cannot starve matching/publishing. A
`FeedRun` that outlives its stage timeouts without progressing is reaped by the
`Feeds\Console\ReapStalledFeedRuns` scheduled command into `failed` with `STALLED`.

### Idempotency

- `StartFeedRun` computes an idempotency key — `schedule:{source}:{slot}` for scheduled runs,
  `manual:{uuid}` for merchant/staff-triggered ones — unique per source; a duplicate trigger for the
  same slot is a no-op.
- `FetchFeedPayload` streams the payload to the private disk and hashes it (sha256); an unchanged
  checksum against `feed_sources.last_checksum` short-circuits the run to `completed`/`unchanged`
  without re-parsing, re-matching or re-publishing.
- `ParseFeedPayload` deletes its own run's staging `feed_items` rows before parsing, so the job is safe
  to retry from the start.
- The single-active-run constraint (state machine above) is the mechanism that prevents two runs for
  one source overlapping.

### Reject threshold

`NormaliseFeedItems` validates every row (`Feeds\Validation\FeedItemValidator`, `Gtin`,
`DuplicateSkuTracker`); if the rejected-row ratio exceeds `comparo.feeds.max_rejected_ratio` (default
0.2, A-09) the run fails as `REJECT_THRESHOLD_EXCEEDED` and **nothing is published** — a systematically
broken feed cannot silently zero out a merchant's live offers row by row.

### Reconciliation (D-25)

Only **published** runs count towards reconciliation. A SKU owned by the source and absent from the
current run increments `merchant_products.missing_run_count`; the listing's offer is deactivated
(`is_active = false`, `deactivated_at`, `deactivation_reason = missing_from_feed`) after
`comparo.feeds.missing_runs_before_deactivation` (2) consecutive misses **or**
`unseen_days_before_deactivation` (7) days unseen, whichever comes first — never deleted. A SKU that
reappears reactivates. **Mass-removal guard:** if one run would deactivate more than
`mass_removal_ratio` (50%) of the source's active listings, the deactivation is held and the run finishes
as `published_with_warnings` instead of silently emptying a merchant's catalogue; sources with fewer
than `mass_removal_min_offers` (10) live offers are exempt (a small merchant's normal churn should not
need review). Unchanged-checksum runs still refresh `last_seen_at`/`source_updated_at` when
`comparo.feeds.unchanged_refreshes_freshness` is true (default; A-11) — the merchant re-served the same
data, so freshness is real even though nothing changed.

### Security (SSRF, XML, uploads)

- **SSRF:** `Feeds\Fetching\DestinationGuard` (pure) + `HostResolver`/`DnsHostResolver` allow only
  http/https on ports 80/443 with no userinfo; every A/AAAA record for the host is resolved and
  rejected if private/loopback/link-local/CGNAT/reserved/multicast/IPv4-mapped; the resolved IP is
  pinned for the actual connection (no re-resolution after the check — no DNS-rebinding window); up to
  3 redirects are followed manually and re-validated per hop, with no https→http downgrade; connect
  timeout 5s, total timeout 120s; the response body is capped by streamed byte count (checked after
  gzip decompression too); content-type is allow-listed; resolved IPs are never echoed back to the
  merchant in error messages.
- **XML:** `Feeds\Parsing\XmlFeedParser` uses `XMLReader` with `LIBXML_NONET`, never `LIBXML_NOENT` or
  DTD loading, and rejects any payload containing `<!ENTITY` outright.
- **Uploads:** `Feeds\Actions\StoreFeedUpload` validates via a Form Request (type, size, `finfo` MIME
  sniff, not just the extension), stores to the private disk at `feeds/{merchant}/{uuid}`, never a
  public disk, never executable; 30-day payload retention (`Feeds\Console\PruneFeedData`).
- **CSV exports** of run errors (`merchant.feeds.runs.errors.export`) neutralise formula injection
  (leading `=`/`+`/`-`/`@` escaped) before the response is streamed.

### Retention

Feed payloads: 30 days. `feed_items`: 7 days except the last 2 successful runs per source (kept for
support/debugging). `feed_errors` are capped per run+code even before pruning. `feed_runs` and
`matching_decisions` are not pruned by this pipeline (append-only history). All figures default pending
D-09; see `docs/privacy/data-retention.md`.

### Snapshot writing — amends ADR-0003

ADR-0003 said price history is append-only but did not fix *where* it is written. Building the publish
path showed that writing it from a `PriceObserved`-style listener risks the snapshot committing after,
or independently of, the offer row it describes (a crash between the two leaves them inconsistent, and
a queued listener could run the write out of order under retries). **Decision: `RecordPriceSnapshot`
is called by `Offers\Actions\PublishOffer` inside the same database transaction that writes the offer
row, never from a listener.** `Pricing\History\SnapshotPolicy` (pure) decides *whether* a row is written
by comparing the observed price/availability against the offer's last snapshot, not its current row:
no previous snapshot → `first_seen`; price or currency changed → `price_change`; availability changed
→ `availability_change`; otherwise, if the last snapshot's UTC date is earlier than the observation's →
`scheduled` (at most one unchanged-price snapshot per offer per UTC day); otherwise no row is written at
all (same-day re-runs are idempotent, satisfying deviation #7). `Pricing\History\SnapshotSource`
(`feed`, `merchant_api`, `manual`, `prototype_demo`) records provenance on each row.

## Consequences

- Snapshot writes can never diverge from the offer they describe, at the cost of `PublishOffer` owning
  a second table's writes inside its transaction (acceptable: both are Offers/Pricing domain writes,
  and `Offers\Actions → Pricing\Actions` is an allowed dependency).
- The mass-removal guard and small-feed exemption need their own review-queue surface for merchants (a
  `published_with_warnings` run currently needs the run detail page to explain *why*; no dedicated
  "held deactivations" list exists yet — known gap, see `docs/implementation-status.md`).
- Idempotency keys and the single-active-run constraint together mean a merchant mashing "Run now"
  cannot double-run a feed; the UI surfaces `FeedRunAlreadyActive`/`FeedRunNotAllowed` instead of
  queuing a second run.

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| Write `price_snapshots` from an after-commit `OfferPublished` listener | Reintroduces the divergence risk this ADR exists to close; a listener failure or reorder can desync history from the offer |
| Deactivate missing SKUs immediately (no `missing_run_count` grace) | One transient feed hiccup would flicker offers on and off; D-25 needs a grace window |
| No mass-removal guard | A malformed feed (e.g. an empty payload parsed as zero rows) would silently deactivate an entire merchant's catalogue in one run |
