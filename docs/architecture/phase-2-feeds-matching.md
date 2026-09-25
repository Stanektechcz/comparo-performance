# Phase 2 design — merchant feeds + canonical matching

Status: **implemented** (2026-09-25; docs refreshed to match the code, see ADRs 0012–0015). Synthesised
from five specialist analyses
(database, feeds, matching, QA/parity, architecture). Where the prototype code and the root specs
disagree, the code wins (source-of-truth order in `CLAUDE.md`); every deliberate deviation is listed
in §9 and carried into ADRs 0012–0015.

**Exit flow:** feed URL/upload → fetch (SSRF-guarded) → parse → normalise + validate → match →
publish (merchant_products → offers → price_snapshots) → reconcile missing SKUs → product page shows
the new offer/price. A merchant can create a feed, map fields, run it, inspect the run and its errors,
review matches and see offers update.

## 1. Module boundaries (namespaces)

| Namespace | Contents | Purity |
|---|---|---|
| `App\Domain\Feeds` | enums (`FeedFormat`, `FeedSourceStatus`, `FeedRunStatus`, `FeedRunOutcome`, `FeedRunTrigger`, `FeedItemValidationStatus`, `FeedItemMatchStatus`, `FeedErrorSeverity`, `FeedErrorCode`, `FeedTransport`, `FeedUrlMask`) | — |
| `Feeds\Parsing` | `FeedParser` interface; `CsvFeedParser`, `XmlFeedParser`, `JsonFeedParser`, `FeedParserFactory`; `ParseOptions`; `RawFeedRow` | stream IO only, no DB |
| `Feeds\Mapping`, `Feeds\Normalisation`, `Feeds\Validation` | `FieldMapping`, `FeedRowMapper` → `NormalisedFeedItem` \| `RowRejection`; `Gtin`; `FeedItemValidator`, `DuplicateSkuTracker` | **pure** |
| `Feeds\Fetching` | `FeedFetcher` (Laravel HTTP client), `DestinationGuard` (pure), `HostResolver`/`DnsHostResolver`, `PayloadWriter` | IO |
| `Feeds\Actions` | `CreateFeedSource`, `UpdateFeedSource`, `SaveFeedMapping`, `UpdateFeedCredentials`, `ChangeFeedSourceStatus`, `StartFeedRun`, `CompleteFeedRun`, `FailFeedRun`, `CancelFeedRun`, `StoreFeedUpload`, `ReconcileMissingListings` | DB, audited |
| `Feeds\Pipeline` | `FeedRunPipeline` (queue/stage constants), `FeedPayloadImporter`, `FeedItemWriter`, `FeedItemListing`, `MappingResolver`, `FeedStorage`, `FeedSourceSettings` | DB + IO |
| `Feeds\Lifecycle` | `FeedRunTransitions`, `FeedSourceLifecycle` (compare-and-swap state changes), `FeedActorKind` | DB |
| `Feeds\Listeners` | `PublishLatestObservation` (queued, reacts to `Matching\Events\ProductMatched`) | DB |
| `Feeds\Console` | `ScheduleDueFeeds`, `ReapStalledFeedRuns`, `PruneFeedData` (scheduled commands) | DB |
| `Feeds\Exceptions` | `FeedRunAlreadyActive`, `FeedRunNotAllowed`, `InvalidFeedTransition`, `FeedRunFailure` | — |
| `Feeds\Jobs`, `Feeds\Queries`, `Feeds\Events` | pipeline jobs (`FetchFeedPayload`, `ParseFeedPayload`, `MatchFeedItems`, `PublishFeedRun`, `FinalizeFeedRun`); merchant-scoped queries taking `MerchantContext`; `FeedImported`, `FeedFailed` | — |
| `App\Domain\Matching\Engine` | `ProductMatcher`, `MatchingPolicy` (`prototypeV1()`), DTOs `FeedItemFacts`, `CandidateProduct`, `BrandAliasSet`, `MatchResult`, `MatchPart`, `MatchPartLabel`, enums `MatchSignal`, `MatchLevel`, `MatchBucket` | **pure** |
| `Matching\Queries` | `CandidateProducts` (EAN ∪ brand/alias ∪ brand-in-title narrowing, **ordered by product id**), `ActiveMatchingPolicy`/`ActivePolicy`, `MerchantMatchingQueue`, `StaffMatchingQueue`, `MatchingCatalogue`, `DecisionHistoryEntry` and related review-queue queries | DB read |
| `Matching\Actions` | `MatchListing`, `DecideMatch`, `Rematch`, `ManualLink`, `ProposeProductCandidate`, `ResolveProductCandidate`, `ResolveConflict`, `ComplianceHolds` | DB, audited |
| `Matching\Contracts` | `ComplianceHoldCheck` (the only way Matching reads Compliance) | — |
| `App\Domain\Shared\Text` | `TextFold` (`fold`, `canonical`, `tokens`), `TitleSimilarity` | **pure** |
| `App\Domain\Compliance` | `ComplianceDecision`, `ComplianceStatus`, `MarketComplianceHold` (implements `Matching\Contracts\ComplianceHoldCheck` — the adapter Matching depends on) | DB read |
| `App\Domain\Offers\Actions` | `UpsertListing` (sole writer of merchant_products identity), `LinkListing`, `PublishOffer`, `DeactivateOffer`, `ConfirmListingsSeen` (bulk freshness confirmation for unchanged-checksum runs) | DB |
| `App\Domain\Pricing\Actions` / `Pricing\History\SnapshotPolicy` | `RecordPriceSnapshot` (sole runtime writer of price_snapshots), `RecheckProductAnomalies` (re-flags anomalies after a publish) / pure reason policy | DB / pure |
| `App\Domain\Pricing\Anomalies` | `PriceAnomalyDetector`, `PriceAnomalyFinding` (pure detection used by `RecheckProductAnomalies`) | **pure** |
| `App\Domain\Platform\Audit` | `AuditLogger`, `AuditAction`, `AuditActor`, `AuditRedactor`, `AuditChanges` | DB |
| `App\Domain\Platform\Features` | `Feature` enum, `FeatureFlags` service — **config-only** (reads `config('features.*')`; no DB-backed store, Pennant deferred) | config |
| `App\Domain\Merchants\MerchantContext` | active merchant + role (session-selected) | — |

**Allowed dependencies:** Feeds → Matching\Actions, Matching\Events, Matching\Contracts,
`ListingMatchStatus`, Offers\Actions, Pricing\Actions, `Pricing\History\SnapshotSource`, Compliance
queries, Shared. Matching → Catalog queries, Offers\Actions, Platform\Audit, Platform\Features, Shared.
Compliance → Matching\Contracts (implements `ComplianceHoldCheck`, does not depend on Matching).
Offers\Actions → Pricing\Actions.
**Forbidden:** Matching → Feeds; Offers/Pricing → Feeds/Matching; Feeds → `Pricing\LandedPrice`,
`Offers\Ranking`, `PriceSnapshot`; `Matching\Engine` → anything but Shared; any Commercial/Affiliate
input into Matching or Ranking. Enforced by `tests/Architecture`.

## 2. Schema (additive migrations 2026_09_25_1007xx–1019xx)

- `matching_policies` — versioned like `ranking_versions` (single-active partial unique index), seeded `prototype-v1` = intel.js Engine 2.0 weights/thresholds.
- `feed_sources` — merchant, name, format (xml|csv|json), transport (url|upload|api_push|manual_upload), status (draft|active|paused|error|disabled), url (≤2048), `credentials` (`encrypted:array`, hidden), country (market) nullable = all, currency, encoding, delimiter, record element, availability_map json, interval_minutes, last/next run, last_success_at, last_checksum, consecutive_failures.
- `feed_mappings` — versioned `field_map` json, single `is_current` per source (partial unique), content immutable.
- `feed_runs` — trigger (manual|schedule|api), status (queued|fetching|parsing|normalizing|matching|publishing|completed|failed|cancelled), outcome (published|published_with_warnings|unchanged), idempotency_key (unique per source), checksum, correlation_id, payload path/bytes/purged_at, **all metrics** (rows_read, rows_valid, rows_invalid, rows_matched, rows_suggested, rows_unmatched, rows_compliance_hold, offers_created, offers_updated, offers_unchanged, offers_deactivated, offers_reactivated, price_changes, anomalies, warnings, errors, duration_ms), failure_code/reason, stage timestamps; partial unique index = one non-terminal run per source.
- `feed_items` — per-run staging: row number, sku, external_id, ean, raw attributes (brand/pack/variant/category), normalised money/availability/stock/urls, raw_payload json, content_hash, validation_status, match_status, match_score, match_parts json, suggested product, merchant_product_id, diff_action. Pruned by retention job.
- `feed_errors` — code + severity + field + message_params json (rendered from translations; no free text), row_number kept after pruning; capped per run+code.
- `merchant_products` += feed_source_id, external_id, raw attributes, raw_payload, content_hash, status (active|missing|delisted), missing_run_count, match_status, match_score, matched_at, last_seen_run_id, current_matching_decision_id; review-queue partial index.
- `matching_decisions` — **append-only** (model guard + DB triggers): merchant_product_id, merchant_id, feed_run_id, kind (auto|suggested|manual|rejected|rematch|unlinked), product_id, previous_product_id, policy, score, components json, decided_by_user_id (no FK — see R3), reason, supersedes_id (unique, linear chain), decided_at.
- `matching_conflicts` (+ `matching_conflict_values`) — staff queue (field conflicts, compliance hold).
- `product_candidates` (+ `product_candidate_sources`) — new-product proposals; creation of canonical products stays a staff catalogue action (Phase 8).
- `brand_aliases`; `ingredient_product.is_listed` (the matcher uses only listed ingredients — prototype `p.ingredients`).
- `offers` += deactivated_at, deactivation_reason, source (`feed`|`prototype_demo`|…), last_feed_run_id (no FK on SQLite: avoid table rebuilds that drop the partial index).
- `price_snapshots` += index on feed_run_id (FK on PostgreSQL only; a SQLite rebuild would drop the append-only triggers).
- Every tenant row carries `merchant_id`; composite FKs `(parent_id, merchant_id)` where cheap.

## 3. Feed contract

Required: `merchant_sku`, `title`, `price`, `currency`, `availability`, `product_url`.
Optional: `external_id`, `gtin/ean`, `brand`, `category`, `variant`, `pack_size`, `stock`,
`reference_price` (old price), `image_url`, `shipping_hint` (stored raw only — landed shipping stays
merchant-zone based, D-07), `updated_at`. Money parsed from decimal strings into minor units using
`currencies.minor_unit`; never floats.

**Error taxonomy (`FeedErrorCode`)** — run-fatal: `UNREACHABLE_URL`, `HTTP_ERROR`, `FETCH_TIMEOUT`,
`BLOCKED_DESTINATION`, `AUTH_FAILED`, `PAYLOAD_TOO_LARGE`, `UNSUPPORTED_CONTENT_TYPE`,
`UNSUPPORTED_ENCODING`, `PARSER_ERROR`, `EMPTY_FEED`, `ROW_LIMIT_EXCEEDED`,
`REJECT_THRESHOLD_EXCEEDED`, `STALLED`, `FETCH_DISABLED` (the `feed-url-fetch` feature flag is off),
`INTERNAL_ERROR` (unexpected exception, never leaks internals to the merchant). Row-reject: `MISSING_SKU`, `MISSING_REQUIRED_FIELD`,
`INVALID_PRICE`, `INVALID_CURRENCY`, `INVALID_AVAILABILITY`, `INVALID_URL`, `DUPLICATE_SKU` (first
wins), `SKU_OWNED_BY_OTHER_SOURCE`, `FIELD_TOO_LONG`, `IMPOSSIBLE_DISCOUNT`. Row-warning (still
published): `INVALID_GTIN`, `MISSING_GTIN`, `UNKNOWN_BRAND`, `INVALID_STOCK`, `INVALID_IMAGE_URL`,
`URL_DOMAIN_MISMATCH`. `MASS_REMOVAL_HELD` (§7) marks a run-level warning when reconciliation withholds
a mass deactivation. Match outcomes are statuses, not errors (`UNMATCHED_PRODUCT` is shown as a
status with an action). Every code has a merchant-facing, actionable message in `lang/en/feeds.php`.

## 4. State machines

- **FeedSource:** draft → active (first successful run) ↔ paused (merchant); active → error (3
  consecutive failures, `AUTH_FAILED`, `BLOCKED_DESTINATION`); error → active (successful run);
  any → disabled (staff); disabled → draft (staff). Only `active` is scheduled; manual runs from
  draft/active/error.
- **FeedRun:** queued → fetching → parsing → normalizing → matching → publishing → completed;
  fetching → completed(outcome `unchanged`) on identical checksum; any non-terminal → failed;
  queued…matching → cancelled; publishing is not cancellable. Transitions are compare-and-swap
  (`UPDATE … WHERE id = ? AND status = ?`); a job that loses the race exits quietly.

## 5. Pipeline (queues `feed-import`, `matching`, `pricing`)

Five jobs, not six: `StartFeedRun` (idempotency key `schedule:{source}:{slot}` / `manual:{uuid}`; one
active run per source) → `FetchFeedPayload` (SSRF guard, streamed to the private disk with sha256;
unchanged checksum → complete as `unchanged`, and confirms freshness on existing listings via
`Offers\Actions\ConfirmListingsSeen` instead of re-processing) → `ParseFeedPayload` (**stage 2:
"parsing → normalizing" is one job**, not two — deletes its own staging `feed_items` rows first so it
is re-runnable, then streams, maps, normalises and validates every row via `FeedPayloadImporter`;
duplicate SKUs, reject threshold 20% → failed `REJECT_THRESHOLD_EXCEEDED`, nothing published) →
`MatchFeedItems` (writes the listing identity via `Offers\Actions\UpsertListing` — the sole writer of
merchant_products identity — then reuses the listing's current decision when its facts fingerprint is
unchanged, otherwise runs `Matching\Actions\MatchListing`; compliance hold when the product is blocked
in the feed market) → `PublishFeedRun` (one transaction per chunk, `published_at` guard; `PublishOffer`
for auto/confirmed matches; then `ReconcileMissingListings`, **then**
`Pricing\Actions\RecheckProductAnomalies` re-evaluates anomaly flags for every product touched by the
run) → `FinalizeFeedRun` (metrics, source state, `FeedImported`/`FeedFailed`). There is no separate
`NormaliseFeedItems` job. Every job: `tries`, `timeout`, `backoff()`, failure transition, ids only in
payloads, correlation id restored into `Context`. Queue `retry_after` must exceed the longest job
timeout (dedicated connection settings + Horizon supervisors per queue; queue connection defaults are
being finalised alongside this doc pass — see `docs/implementation-status.md`).

**Auto-publish:** bucket `auto` (≥ 90) publishes when feature `matching-auto-publish` is on (default
on in local/testing, configurable); bucket `confirm` (65–89) waits in the review queue unpublished;
`unmatched` (< 65) creates/updates a `product_candidates` proposal only on explicit merchant action.

## 6. Publishing, snapshots, events

- `PublishOffer` locks the offer, compares terms, writes only on change, and calls
  `RecordPriceSnapshot` **inside the same transaction** (history never diverges from the current price;
  amendment to ADR 0003). `SnapshotPolicy` (pure): first publish → `first_seen`; price/currency change
  → `price_change`; availability change → `availability_change`; else first observation of a new UTC
  day → `scheduled`; otherwise **no row** (same-day re-runs are idempotent).
- Events (final readonly, ids + scalars, `ShouldDispatchAfterCommit`): `Offers\Events\OfferPublished`,
  `OfferDeactivated`, `OfferRelinked`; `Pricing\Events\PriceChanged` (old/new minor, currency, reason);
  `Matching\Events\ProductMatched` (queued listener `Feeds\Listeners\PublishLatestObservation`);
  `Feeds\Events\FeedImported`, `FeedFailed`. Full producer/listener/sync-or-queued table:
  `docs/architecture/event-matrix.md`.
- Bulk offer writers that are not per-row publishes: `Offers\Actions\ConfirmListingsSeen` (refreshes
  freshness on an unchanged-checksum run, §5) and `Pricing\Actions\RecheckProductAnomalies` (re-flags
  price anomalies after a run publishes, using `Pricing\Anomalies\PriceAnomalyDetector`). An
  unchanged-checksum run writes **no** `scheduled` snapshot for its listings — nothing was observed, so
  `SnapshotPolicy` never runs for it; only a normally-processed run can produce a `scheduled` row.
- Cache: a synchronous after-commit listener bumps product versions (both products on relink). The
  existing `Offer::saved` hook stays as a safety net for direct writes but only bumps when the model
  actually changed and defers the bump until after commit.

## 7. Reconciliation (D-25)

Only published runs count. `merchant_products.missing_run_count` is **derived**, not a raw counter fed
by an external signal: it is incremented by `ReconcileMissingListings` for a SKU owned by the source and
absent from the current run, and reset when the SKU reappears. Deactivate (`is_active=false`,
`deactivated_at`, `deactivation_reason=missing_from_feed`; the reconciliation reason surfaced to the
merchant is `not_seen`) after 2 consecutive misses or 7 days unseen (config). Never delete. Reappearing
SKUs reactivate. Mass-removal guard: if one run would deactivate > 50% of the source's active listings,
hold the deactivation and finish `published_with_warnings` (error code `MASS_REMOVAL_HELD`); sources
with fewer than `mass_removal_min_offers` = 10 live offers are exempt from the guard. Unchanged-checksum
runs refresh `last_seen_at`/`source_updated_at` via `Offers\Actions\ConfirmListingsSeen` (config
`comparo.feeds.unchanged_refreshes_freshness`, default true — the merchant re-served the same data).

## 8. Security

- SSRF: http/https, ports 80/443, no userinfo; resolve all A/AAAA records via `HostResolver`, reject
  any private/loopback/link-local/CGNAT/reserved/multicast/IPv4-mapped address; pin the resolved IP;
  ≤ 3 manually-followed redirects re-validated per hop, no https→http downgrade; connect 5 s / total
  120 s; streaming byte cap (also after gzip); content-type allow-list; never echo resolved IPs.
- XML: `XMLReader` with `LIBXML_NONET`, no `LIBXML_NOENT`/DTD loading, reject `<!ENTITY`.
- Uploads: Form Request type + size + finfo MIME check, private disk `feeds/{merchant}/{uuid}`,
  never public, never executed; 30-day payload retention.
- Credentials: encrypted cast, hidden, presenters expose only `hasCredentials`/masked URL; changing
  them requires password confirmation and is audited as `{credentials_changed: true}`.
- Isolation: `/merchant/*` resolves resources through `MerchantContext`-scoped queries (foreign id →
  404) then policies (owner/manager mutate, analyst read-only, credentials owner-only); staff
  `/admin/catalogue/matching` requires `matching.review` (+ `offers.manage` for relinking published
  listings). Negative tests for every route, including a user who belongs to merchants A and B.
- CSV exports of errors neutralise formula injection.

## 9. Deliberate deviations from the prototype (documented, tested)

| # | Prototype | Laravel | Why |
|---|---|---|---|
| 1 | Pasted feeds parsed client-side, max 25 rows, never persisted | real persisted pipeline | the prototype only simulates |
| 2 | HTML `matchItem` (0.98/0.95 bands) | intel.js Engine 2.0 (C-23) | C-23: one canonical engine |
| 3 | Matcher scores every product; returns product #1 at score 0 | narrowed candidates (EAN ∪ brand ∪ brand-in-title); no candidate → no suggestion | without EAN/brand signals the max score is 44 < 65, so buckets are unchanged; parity is proven on the full catalogue |
| 4 | JS `constructor` token quirk in Jaccard (prototype-chain lookup) | not replicated | prototype bug |
| 5 | Confirm/New/Skip only toast | persisted decisions + audit | never fake completeness |
| 6 | Invalid GTINs used silently | `INVALID_GTIN` warning, raw EAN still matched exactly | seed EANs are 10–11 digits |
| 7 | "Snapshot once per day regardless" + "no snapshot for unchanged" | at most one unchanged (`scheduled`) snapshot per offer per UTC day | satisfies both specs, idempotent same-day |

## 10. Open decisions (safe defaults applied)

Recorded in `docs/autonomy/OPEN-DECISIONS.md` (A-05…): staff prefix `/admin` (Horizon stays at
`/staff/horizon`); config-backed feature flags now, Pennant later only with dependency approval;
`URL_DOMAIN_MISMATCH` is a warning until `/go` enforces destinations (Phase 5); feed-level shipping
stored raw only; one owning source per merchant SKU; canonical product creation from candidates
deferred to Phase 8; snapshot product attribution after a mismatch correction is derived from the
append-only decision history (aggregation must exclude periods later marked `rejected`).
