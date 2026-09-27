# Quality gates

| Gate | When | Must be green |
|---|---|---|
| **A — Task** | before a task leaves IN_PROGRESS | focused tests for the touched domain, Pint on touched files, Larastan on touched paths |
| **B — Slice** | before a task becomes DONE | all affected domain + feature tests, full Larastan, `comparo:verify-prototype`, parity `--check` when offers/markets/pricing/ranking/trust/risk/importer changed |
| **C — Phase** | before a phase is marked complete / merged to `main` | full Pest on SQLite **and** PostgreSQL, Pint, Larastan, parity `--check`, prototype integrity, `types:check`, `check`, `build`, `build:ssr`, review council (architecture, security, QA, performance, frontend) with no open BLOCKER/MAJOR |
| **D — Release** | Phase 19 | C + production configuration audit (`comparo:doctor`), security review, migration fresh + upgrade path, queue/scheduler/mail/search/storage checks, no-dummy-data and no-fake-functionality audits |

## Commands

```bash
export PATH="/c/php:$PATH"               # PowerShell: $env:Path = "C:\php;$env:Path"
php artisan comparo:verify-prototype
php artisan test --compact
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=2G
node tools/prototype-parity/export-fixtures.mjs --check
npm run types:check && npm run check && npm run build:ssr   # build:ssr includes the client build
```

PostgreSQL run: see `docs/development/setup.md` → "Verifying against PostgreSQL without Docker".

## Trend (never game these numbers)

| Date | Point | Tests | Assertions | Larastan errors | Frontend | Parity fixtures | Prototype | Known blockers |
|---|---|---|---|---|---|---|---|---|
| 2026-09-25 | end of Phase 0 + first Phase 1 slice | 136 | 2 715 | 0 | green | current | 137/137 | no git |
| 2026-09-25 | baseline commit `fe8c42b` (+ prototype integrity command) | 142 | 2 725 | 0 | green | current | 137/137 | no remote / CI never ran |
| 2026-09-25 | Phase 2 Gate C (feeds + matching) | 1 298 | 7 247 (SQLite) / 7 244 (PostgreSQL) | 0 | green (1 lint warning in the parity exporter) | current (+ matching, anomalies) | 137/137 | no remote / CI never ran; no browser check of authenticated pages |

**Correction (2026-09-25, during Phase 3):** the Phase 2 Gate C row recorded `npm run check` as green, but
with `denyWarnings` the single lint warning in the parity exporter made it exit 1. Fixed in `cd15292`; gate
runs now record the exit code, not only the summary line.
| 2026-09-26 | Phase 3 Gate C (search & discovery) | 1 803 (1 792 passed, 11 skipped: Meilisearch) | 9 675 (SQLite) / 9 672 (PostgreSQL) | 0 | green, all exit 0 | current (+ search) | 137/137 | no remote / CI never ran; Meilisearch contract never run against a real server |
| 2026-09-26 | Phase 4 wave 1 (schema + pure engines: P4-00, P4-02, P4-03, P4-04) | 2 112 (2 101 passed, 11 skipped: Meilisearch) | 10 852 (SQLite) | 0 | green, `npm run check` exit 0 | current | 137/137 | remote + CI now exist (ran green on PR #1); Meilisearch also verified locally against a real server (contract dataset 48/48) |
