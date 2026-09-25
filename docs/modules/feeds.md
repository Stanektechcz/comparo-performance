# Module: Feeds

Namespace `App\Domain\Feeds`. ADRs: 0013 (amends 0003), 0015. Design: `docs/architecture/phase-2-feeds-matching.md`.

## Responsibilities

- Merchant feed source configuration (transport, format, mapping, credentials, schedule).
- Fetching a feed payload (URL, guarded against SSRF) or accepting an upload.
- Parsing (CSV/XML/JSON), normalising and validating rows into staged `feed_items`.
- Driving the run through its state machine and publishing valid, matched rows as offers.
- Reconciling SKUs that disappeared from a feed (deactivate, never delete).
- Retention/pruning of feed payloads and staging data.

Owned tables: `feed_sources`, `feed_mappings`, `feed_runs`, `feed_items`, `feed_errors`. Feeds also
writes into `merchant_products` (feed-sourced columns only — `Offers\Actions\UpsertListing` is the sole
writer of listing identity) and triggers `Offers\Actions\PublishOffer`/`DeactivateOffer`.

## Pipeline (queue `feed-import`)

| Job | Input → output | Notes |
|---|---|---|
| `StartFeedRun` (action) | source + trigger → `feed_runs` row (`queued`) | idempotency key `schedule:{source}:{slot}` / `manual:{uuid}`; one active run per source |
| `FetchFeedPayload` | source → payload file + sha256 checksum | SSRF-guarded (`Fetching\DestinationGuard`/`HostResolver`); unchanged checksum → `completed`/`unchanged`, refreshes freshness via `Offers\Actions\ConfirmListingsSeen` instead of reprocessing |
| `ParseFeedPayload` | payload → `feed_items` + `feed_errors` | **parses and normalises in one job** (stage "parsing → normalizing"); deletes its own prior staging rows first (safe to retry); reject ratio > `max_rejected_ratio` (0.2) → `REJECT_THRESHOLD_EXCEEDED`, nothing published |
| `MatchFeedItems` | valid `feed_items` → matched/suggested/unmatched | writes listing identity via `Offers\Actions\UpsertListing`, then reuses the current decision when the facts fingerprint is unchanged, else `Matching\Actions\MatchListing`; compliance hold when blocked in market |
| `PublishFeedRun` | matched rows → offers + snapshots | one DB transaction per chunk; `PublishOffer` for auto/confirmed matches; then `Feeds\Actions\ReconcileMissingListings`; then `Pricing\Actions\RecheckProductAnomalies` |
| `FinalizeFeedRun` | run → terminal state | writes metrics, updates source status, dispatches `FeedImported`/`FeedFailed` |

Every job declares `tries`, `timeout` and `backoff()`, transitions the run to `failed` on an
unrecoverable error, passes only ids in its payload, and restores the correlation id into `Context` at
the start (see ADR-0014). `Feeds\Console\ReapStalledFeedRuns` fails a run that outlives its stage
timeouts.

## State machines

| | States | Transitions |
|---|---|---|
| `FeedSource` (`FeedSourceStatus`) | draft, active, paused, error, disabled | draft→active (first success); active↔paused (merchant); active→error (3 fails / `AUTH_FAILED` / `BLOCKED_DESTINATION`); error→active (success); any→disabled, disabled→draft (staff only) |
| `FeedRun` (`FeedRunStatus`) | queued, fetching, parsing, normalizing, matching, publishing, completed, failed, cancelled | linear happy path; fetching→completed(`unchanged`) on identical checksum; any non-terminal→failed; queued…matching→cancelled; **publishing is not cancellable** |

Transitions are compare-and-swap (`Feeds\Lifecycle\FeedRunTransitions`, `UPDATE … WHERE status = ?`); a
job that loses the race exits quietly.

## Security

SSRF guard (allow-listed ports/schemes, DNS-resolved and pinned, private/internal ranges rejected,
capped redirects, no downgrade), streaming byte caps, content-type allow-list, `XMLReader` with
`LIBXML_NONET`/no DTD/no `<!ENTITY`, upload MIME sniffing + private disk, encrypted+hidden credentials
(password-confirmed changes, audited), CSV export formula-injection neutralisation. Full detail: ADR-0013 §Security.

## Reconciliation (D-25)

Only published runs count. A SKU absent from a published run increments the derived
`merchant_products.missing_run_count`; deactivates after 2 consecutive misses or 7 days unseen
(`deactivation_reason = missing_from_feed`, surfaced reason `not_seen`); reactivates on reappearance;
never deleted. Mass-removal guard holds deactivation and finishes `published_with_warnings`
(`MASS_REMOVAL_HELD`) when a run would deactivate more than 50% of a source's active listings, except
sources with fewer than 10 live offers.

## Invariants

- `feed_items`/`feed_errors` are staging data, pruned by `Feeds\Console\PruneFeedData` per
  `docs/privacy/data-retention.md`; `feed_runs`, `matching_decisions` and `price_snapshots` are not.
- A feed can never write directly to `Pricing\LandedPrice` or `Offers\Ranking` — only through
  `Offers\Actions`/`Pricing\Actions` (enforced by `tests/Architecture`).
- Every merchant-triggered write is audited (`Platform\Audit\AuditLogger`, ADR-0014) and isolated to the
  active `MerchantContext` merchant (ADR-0015).

## Known gaps

- `FeedTransport::ApiPush` has no controller/route yet.
- Feed-level `shipping_hint` is stored raw only; landed shipping still comes from merchant shipping
  zones (D-07).
- The availability map (`feed_sources.availability_map`) has no dedicated editing UI yet.
- `FeedImported`/`FeedFailed` have no notification listener (no merchant email on run completion/failure).
