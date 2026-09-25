# Handoffs

Newest first. Each entry states where work stopped and the exact next step, so a new session can
continue without reconstructing context.

## 2026-09-25 — Phase 2 complete (Gate C), Phase 3 starting

- Phase 2 (merchant feeds + canonical matching) is implemented, reviewed (security PASS; architecture,
  performance and frontend findings fixed in `7a1df7b` / `501b999`), documented (ADRs 0012–0015,
  matrices, module docs) and merged into `main` by fast-forward.
- Gate C: 1 298 Pest tests green on SQLite and PostgreSQL (embedded, port 55432, procedure in
  `docs/development/setup.md`), Pint, Larastan 0, parity `--check`, types/lint/build:ssr, prototype 137/137.
- Known limits: no browser check of authenticated merchant/staff pages (password entry is not automated);
  CI never ran (no remote). Follow-ups F-01…F-10 in `BACKLOG.md`.
- Run PostgreSQL verification with `php -d extension=pdo_pgsql vendor/bin/pest` (not `php artisan test`,
  whose child process loses the `-d` flag) and never run two suites against the same database at once.
- **Next:** Phase 3 — Search & discovery. Branch `phase-3/search`. First task P3-01: read-only analyses
  (prototype search + synonyms in `Comparo Performance.dc.html` / SEARCH.md / INDEXING.md, Scout config,
  index documents, market + compliance filtering, facets, suggest endpoint, analytics events) → design
  doc `docs/architecture/phase-3-search.md` + Phase 3 section in `TASK-GRAPH.md`.

## 2026-09-25 — Autonomous programme start

- Baseline verified (142 tests / 2 725 assertions, Pint, Larastan 0, parity current, frontend green,
  prototype 137/137) and committed as `fe8c42b` on `main`.
- Added `php artisan comparo:verify-prototype` + `docs/prototype/SHA256SUMS`, `.gitattributes`
  byte-protection for prototype files, CI step.
- Created `docs/autonomy/*` and 24 specialist definitions in `.claude/agents/`.
