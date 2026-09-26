# Integrations matrix

Consistent with `docs/autonomy/EXTERNAL-DEPENDENCIES.md` (status vocabulary reused here:
`IMPLEMENTABLE_LOCALLY`, `SANDBOX_AVAILABLE`, `CREDENTIAL_REQUIRED`, `PRODUCTION_ONLY`, `OPTIONAL`).
Scope: integrations Phase 2 (feeds + matching) and Phase 3 (search & discovery) actually touch, plus the
platform integrations they sit on top of.

| Provider | Purpose | Adapter | Credentials | Webhooks | Sandbox | Status | Failure mode |
|---|---|---|---|---|---|---|---|
| Merchant feed endpoints (arbitrary merchant-hosted URLs) | Source of `feed_sources` with `transport=url` | `Feeds\Fetching\FeedFetcher` (Laravel HTTP client) behind `Feeds\Fetching\DestinationGuard`/`HostResolver` (SSRF guard) | Per-source `feed_sources.credentials` (`encrypted:array`, hidden), optional (basic/bearer — `FeedAuthType`) | none (Comparo only pulls) | fixture files + `Http::fake()` in tests | PRODUCTION_ONLY (real merchant onboarding needed; pipeline itself is FUNCTIONAL against fixtures) | Run-fatal `FeedErrorCode` (`UNREACHABLE_URL`, `HTTP_ERROR`, `FETCH_TIMEOUT`, `AUTH_FAILED`, `BLOCKED_DESTINATION`, …) fails the run without publishing; `feed-url-fetch` flag off → `FETCH_DISABLED` |
| Merchant file upload | Source of `feed_sources` with `transport=upload`/`manual_upload` | `Feeds\Actions\StoreFeedUpload` (Form Request validation + private disk) | none external | n/a | local disk in tests | IMPLEMENTABLE_LOCALLY | Form Request rejects bad type/size/MIME before a run is even created |
| Merchant API push (`transport=api_push`) | Planned alternative to polling a URL | none yet | n/a | would be inbound webhook | n/a | NOT IMPLEMENTED | Enum value exists (`FeedTransport::ApiPush`); no controller/route accepts a push today — known gap |
| PostgreSQL 16+ | Primary database for feeds/matching/audit tables | Eloquent / query builder | local instance | n/a | SQLite in tests; embedded-postgres for verification | IMPLEMENTABLE_LOCALLY | Migration/query failure surfaces as a 500; queued jobs retry per their `tries`/`backoff()` |
| Redis (via Horizon) | `feed-import`, `matching`, `pricing` queues | Laravel queue connections + Horizon supervisors | production instance | n/a | database/array drivers locally | PRODUCTION_ONLY | Queue unavailable → jobs pile up undelivered; Horizon dashboard at `/staff/horizon` (`staff.horizon.view`) surfaces this |
| S3-compatible object storage | Feed payload storage (private disk, `COMPARO_FEEDS_DISK`) | Laravel filesystem disk | bucket + keys | n/a | `local` disk in dev/tests | CREDENTIAL_REQUIRED | Falls back to local disk in non-production envs; production without credentials fails payload writes |
| Meilisearch | Production search engine (Scout driver `meilisearch`); required in production — `SearchServiceProvider` refuses the local database engine there | `Search\Engines\MeilisearchSearchEngine` via Scout's `Meilisearch\Client` (`config/scout.php` `meilisearch.{host,key,task_timeout_ms}`) | `MEILISEARCH_HOST`, `MEILISEARCH_KEY` | none (Comparo only writes/reads) | CI service (`getmeili/meilisearch:v1.53.2` in `.github/workflows/tests.yml`, ran green on PR #1) and verified locally against a real server (`v1.53.2`): engine contract dataset 48/48 | CREDENTIAL_REQUIRED for production keys (adapter code itself is FUNCTIONAL — parity-tested behavioural contract, not byte-identical relevance ordering; see `docs/architecture/phase-3-search.md` §3) | `Search\Engines\SearchEngineException` on a task/client error; the local `DatabaseSearchEngine` is the only engine allowed outside production, so a misconfigured non-production env falls back to it rather than failing silently |

## Known gaps

- `api_push` transport has no implementation (enum-only); do not describe it as available in merchant-
  facing copy.
- No affiliate/billing/email-provider integration is touched by Phase 2 feeds/matching work; see
  `docs/autonomy/EXTERNAL-DEPENDENCIES.md` for those.
- The Meilisearch adapter has a CI service and a shared behavioural contract suite
  (`tests/Feature/Search/EngineContractTest.php`). GitHub Actions now runs against the git remote
  `Stanektechcz/comparo-performance` (ran green on PR #1), and the adapter has also been verified locally
  against a real Meilisearch 1.53.2 server (engine contract dataset 48/48) — see
  `docs/implementation-status.md`.
