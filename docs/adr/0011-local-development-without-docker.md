# ADR-0011: Local development without Docker

- Status: Accepted
- Date: 2026-09-25
- Related: ADR-0001, [development/setup.md](../development/setup.md)

## Context

The production target is PostgreSQL 16, Redis 7, Meilisearch and S3-compatible storage. The reference
development machine (Windows 10) has no Docker. PHP 8.4 is installed at `C:\php` and is not on `PATH`;
its `php.ini` does not load `pdo_pgsql`. Horizon requires `ext-pcntl`/`ext-posix`, which do not exist on
Windows.

## Decision

1. **Two supported local profiles:**

   | Concern | Without Docker (reference machine) | With Docker (`compose.yaml`) | CI |
   |---|---|---|---|
   | Database | SQLite `database/database.sqlite` | PostgreSQL 16 (`pgsql`, 5432) | PostgreSQL (target) |
   | Queue | `database` | `redis` (Horizon on Linux/WSL) | `sync` in tests |
   | Cache | `database` | `redis` | `array` in tests |
   | Search | Scout `database` driver | Meilisearch (7700) | — |
   | Mail | `log` or Mailpit if available | Mailpit (SMTP 1025, UI 8025) | `array` |
   | Files | `local` disk | MinIO (9000, console 9001) | `local` |

   `.env.example` defaults to the Docker profile and documents each fallback in comments.
2. **Migrations run on both SQLite and PostgreSQL.** Driver-specific DDL (append-only triggers, partial
   indexes) branches on `DB::getDriverName()`. Tests run on in-memory SQLite (`phpunit.xml`); CI adds a
   PostgreSQL job (planned; `.github/workflows/tests.yml` currently runs `composer ci:check` on PHP 8.3
   without a database service).
3. `composer.json` `config.platform` declares `ext-pcntl` and `ext-posix` (8.4) so `composer install`
   succeeds on Windows. Horizon is not started on Windows; `php artisan queue:work` is used instead.
4. `predis` (pure PHP) is the Redis client so no PHP extension is required.
5. Wayfinder generates TypeScript from routes during `npm run build`/`vp dev` and calls `php artisan`;
   `php` must be on `PATH` for the Node build.

## Consequences

- PostgreSQL-only behaviour (partial-index semantics, `jsonb`, trigger functions) must be covered by the
  CI PostgreSQL job, not only by local SQLite runs.
- Developers on Windows cannot run Horizon locally; queue topology is tested on Linux/CI.
- Setup instructions differ per profile; `docs/development/setup.md` is the single source.

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| Require Docker / Sail | Not available on the reference machine |
| Laravel Herd / native PostgreSQL install | Possible per developer, not required; SQLite covers the domain tests |
| Skip Horizon entirely | Production needs queue supervision and metrics |
