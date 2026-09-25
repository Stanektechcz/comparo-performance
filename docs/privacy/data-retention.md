# Data retention

D-09 (data retention periods) is open; every period below marked **default (D-09 pending)** is a safe
default applied to unblock development, not a signed-off policy. Values with a config key are
operator-adjustable without a code change.

| Entity | Retention | Reason | Deletion / anonymisation | Status |
|---|---|---|---|---|
| Feed payloads (raw fetched/uploaded files, private disk) | 30 days | Debugging a run without keeping merchant data indefinitely | `Feeds\Console\PruneFeedData` deletes the file; `feed_runs.payload_purged_at` records when | default (D-09 pending) |
| `feed_items` (per-run staging rows) | 7 days, **except** the last 2 successful runs per source | Support/debugging window; the last 2 successful runs stay for "what did we actually see" | `Feeds\Console\PruneFeedData` | default (D-09 pending) |
| `feed_errors` | Capped per run + error code (not time-based); the run's own metrics keep the true totals even after rows are capped | Prevent one badly-formed feed from generating unbounded error rows | rows beyond the cap are never written, not pruned after the fact | default (D-09 pending) |
| `feed_runs` | Not pruned | Run history (metrics, status, timestamps) is small per row and is the pipeline's own audit trail | never deleted | default (D-09 pending) |
| `matching_decisions` | Not pruned (append-only by design — DB triggers + model guard) | Legal/operational need to reconstruct how a listing arrived at its current match | never deleted, never updated | Accepted (ADR-0012), not subject to D-09 |
| `price_snapshots` | Not pruned (append-only by design — DB triggers + model guard) | Price history is a core product feature | never deleted; corrections are new rows, never edits (ADR-0003) | Accepted (ADR-0003/ADR-0013), not subject to D-09 |
| `audit_logs` | Not pruned | Privileged-action audit trail (ADR-0014) | never deleted; `actor_id` has no FK to `users` so a user can be deleted without touching this table | default (D-09 pending) — retention period itself is open, append-only-ness is Accepted |
| `matching_conflicts` / `matching_conflict_values` | Not pruned | Staff review queue history | resolved/dismissed rows stay for audit; no purge job | default (D-09 pending) |
| `product_candidates` / `product_candidate_sources` | Not pruned | New-product proposal history | rejected/merged rows stay; no purge job | default (D-09 pending) |
| `feed_sources.credentials` | Lives as long as the source | Needed to run the feed | overwritten on rotation; deleting the source removes the row (cascades) | Accepted (encrypted cast, hidden, redacted in audit logs) |
| `search_queries` (search analytics) | Raw rows 13 months (`comparo.search.analytics.raw_retention_months`); `session_hash` nulled after 90 days (`comparo.search.analytics.session_hash_days`) | Search quality, zero-result demand and click attribution (A-24) | `comparo:search:prune-analytics` (daily 00:50 UTC) nulls `session_hash`, then deletes expired rows with their clicks. Never stored: IP address, user id, user agent, raw session id, raw query. Stored query = folded, whitespace-collapsed, capped at 100 chars, with e-mails, phone-like numbers and other 7+ digit runs redacted (8/12/13/14-digit EAN/GTIN runs kept); `session_hash` = HMAC-SHA256(session id, daily secret derived from APP_KEY + UTC date), so it rotates daily; `is_bot` from a conservative user-agent list | default (D-09 pending, DPO sign-off A-24) |
| `search_clicks` | Deleted with their search (13 months) | Result-click attribution | deleted by `comparo:search:prune-analytics` together with the search row (and by FK cascade). Holds only search id, entity type/id, position, time; accepted only for a same-session search ≤ 30 min old that listed the entity at that position | default (D-09 pending) |
| `search_demand_daily` | Not pruned | Aggregated demand per day, market and redacted query (non-bot search-page rows only); no session hashes or identifiers | read through `SearchDemandReport::topQueries`, which exposes a query only when ≥ 3 sessions (summed daily-distinct sessions) searched it; storage keeps every aggregate | default (D-09 pending) |

## Notes

- Every "default (D-09 pending)" row uses figures the code and config already enforce today
  (`config('comparo.feeds.*')` for the feed-side ones); they are safe, reversible defaults, not
  placeholders that block anything — but they have not been through the DPO/Legal review D-09 assigns.
- The three append-only tables (`matching_decisions`, `price_snapshots`, `audit_logs`) are a distinct
  category: their *retention* is still open under D-09, but their *append-only-ness* (no update, no
  delete, ever) is an accepted architectural decision independent of how long rows are eventually kept.
- No entity in this table is deleted synchronously from a user-facing action; all pruning is scheduled
  (`Feeds\Console\PruneFeedData`, `Search\Analytics\PruneSearchAnalytics`) or has no purge job yet.
- Search analytics config keys (`comparo.search.analytics.session_hash_days`, `raw_retention_months`,
  `click_window_minutes`, `min_demand_sessions`) fall back to the defaults in
  `App\Domain\Search\Analytics\AnalyticsSettings` (90 days, 13 months, 30 minutes, 3 sessions) until they are
  added to `config/comparo.php`. Recent searches shown on the search page live only in the visitor's
  browser (`localStorage`) and are never sent to the server.
