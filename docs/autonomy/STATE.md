# Autonomous development state

Human-readable companion of [`state.json`](state.json). Updated after every integrated task.
A new session resumes by reading `CLAUDE.md`, this file, `state.json` and
`docs/implementation-status.md`, then following [HANDOFFS.md](HANDOFFS.md).

## Now

| Item | Value |
|---|---|
| Phase | **4 — Reviews, orders & purchase verification** (starting; Phases 2 and 3 DONE at Gate C) |
| Slice | Phase 4 analysis → design `docs/architecture/phase-4-reviews-orders.md` → task graph |
| Branch | `main` includes Phases 2 + 3 (fast-forwarded after each Gate C); Phase 4 work happens on `phase-4/reviews-orders` |
| Last green full gate | 2026-09-26 Phase 3 Gate C — Pest 1 803 tests (1 792 passed, 11 Meilisearch skipped) on SQLite (9 675 assertions) and PostgreSQL (9 672), Pint clean, Larastan L7 0 errors, parity current, `types:check` / `check` / `build:ssr` exit 0, prototype 137/137 |
| Next task | see `state.json → next_task` and [TASK-GRAPH.md](TASK-GRAPH.md) |

## Session start protocol

1. Read `CLAUDE.md`, this file, `state.json`, `docs/implementation-status.md`.
2. `git status` · `git branch --show-current` · `git log --oneline -5` (the working tree should be clean).
3. Smoke: `php artisan comparo:verify-prototype` and `php artisan test --compact tests/Architecture tests/Feature/Platform`.
4. Resume `state.json → next_task`. Do not repeat repository archaeology unless state looks inconsistent.

## Session end protocol

Commit green work, update `state.json` + this file, append a dated entry to [HANDOFFS.md](HANDOFFS.md)
with the exact next command/task, list only real external blockers.

## Environment facts (verified 2026-09-25)

- Windows 10, PHP 8.4.16 at `C:\php` (not on PATH): bash `export PATH="/c/php:$PATH"`,
  PowerShell `$env:Path = "C:\php;$env:Path"`. Composer at `~/.claude/runtime/composer/composer.phar`.
- Node 24, npm. Git 2.47 with a configured global identity; **no remote** → CI has never executed.
- No Docker, no Redis, no Meilisearch, no system PostgreSQL. Tests use SQLite in-memory, database/array
  drivers and Scout's collection driver. PostgreSQL verification uses `embedded-postgres` from the
  session scratchpad (procedure in `docs/development/setup.md`).
- Laravel 13.33, Inertia 3, React 19, Tailwind 4, Pest 4, Larastan level 7, Horizon (platform extensions
  faked in composer config for Windows).

## Working agreements

- Orchestrator = main session: selects tasks, assigns specialists, integrates, runs gates, commits.
- Code-writing specialists never share a file set concurrently (see TASK-GRAPH ownership column).
- Git worktrees are not used for code agents yet: `vendor/` and `node_modules/` are untracked, so a fresh
  worktree cannot run the suite without a full install. Parallel code work uses disjoint file ownership on
  the phase branch instead (decision A-02 in [OPEN-DECISIONS.md](OPEN-DECISIONS.md)).
