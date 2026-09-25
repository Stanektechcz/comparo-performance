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
