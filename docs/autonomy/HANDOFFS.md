# Handoffs

Newest first. Each entry states where work stopped and the exact next step, so a new session can
continue without reconstructing context.

## 2026-09-26 (later) — Open items closed, CI connected, Phase 4 ready

- Owner delegated all open decisions: D-01…D-29 decided (ADR-0018, legal/DPO prerequisites marked);
  A-01…A-29 confirmed (A-12 amended). Config changes implied by ADR-0018 are NOT applied yet (next task P4-00).
- Backlog F-01…F-17 resolved except F-14 (deferred: the swap rebuild drops orphans); new F-18 (bulk snapshot).
- Meilisearch adapter verified against a real server (local v1.53.2): contract suite 48/48.
- GitHub: private repo Stanektechcz/comparo-performance (origin). First CI run on main failed on environment
  gaps (Wayfinder not generated before the type check, tests dispatching to redis-long, Vite in class-based
  tests) — fixed in the CI commit; PR #1 (phase-4/reviews-orders → main) runs CI.
- Local verification: 1 868 tests (1 857 passed, 11 Meilisearch skipped) on SQLite and PostgreSQL; the demo
  login for browser checks uses COMPARO_DEMO_PASSWORD in the local .env (never committed).
- **Next:** (1) PR #1 green → merge to main; (2) P4-00 apply ADR-0018 config keys + feature flags (all new
  flags off); (3) P4-01 Phase 4 analyses → docs/architecture/phase-4-reviews-orders.md → task graph → waves.

## 2026-09-26 — Phase 3 complete (Gate C), Phase 4 starting

- Phase 3 (search & discovery) is implemented, reviewed (security APPROVE; 2 blockers — reindex lost update,
  production falling back to the full-scan engine — and all majors fixed in `5833251`), documented (ADR-0016
  search, ADR-0017 multi-currency, docs/modules/search.md, matrices) and merged into `main` by fast-forward.
- Also landed: multi-currency correctness (lowest total, market baselines, ranking inputs, shipping/coupon
  term conversion, `meta.market_min_currency`), cached comparison pages hold scalars only (a cached Money
  object 500'd the product page on serializing stores), deterministic ranking snapshot test, lint gate now
  checked by exit code.
- Gate C: 1 803 tests (1 792 passed, 11 skipped Meilisearch) on SQLite and PostgreSQL, Pint, Larastan 0,
  parity `--check`, types/lint/build:ssr exit 0, prototype 137/137.
- Known limits: Meilisearch adapter never run against a real server (CI service added, CI never executed —
  no remote); no browser check of authenticated pages; follow-ups F-11…F-17 in BACKLOG.md.
- **Next:** Phase 4 — reviews, orders & purchase verification (programme brief §94–104). Branch
  `phase-4/reviews-orders` (created). First task P4-01: read-only analyses — prototype reviews/credibility/
  verification/orders/returns/disputes (REVIEWS.md, ORDERS.md, MODERATION.md, DELIVERY-GUARANTEE.md,
  seed-orders.js, the HTML review/verification flows; parity fixtures for reviews already exported but not
  ported — see tools/prototype-parity/export-fixtures.mjs) + Laravel design (private receipt storage with
  signed URLs, inbound-email adapter + local simulator, affiliate_click_match stub until Phase 5, moderation
  queue, weighted aggregation by credibility, merchant replies, delivery metrics with minimum samples) →
  `docs/architecture/phase-4-reviews-orders.md` + Phase 4 section in TASK-GRAPH.md. Keep the same working
  pattern: analyses → binding design → waves of 3–5 specialists with disjoint files → review council → fixes →
  Gate C on both drivers → merge.
- Operating notes: PHP at C:php (`export PATH="/c/php:$PATH"`); PostgreSQL: embedded instance on port 55432
  (restart with `node start-utf8.mjs` in the scratchpad `pg` folder if down), run with
  `php -d extension=pdo_pgsql vendor/bin/pest`, never two suites against it at once; the Fact-Forcing Gate hook
  asks for facts on first file edits and some Bash commands — state them and retry.

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
