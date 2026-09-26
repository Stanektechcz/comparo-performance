# Staging & deployment runbook

Decision A-39: staging is production-like (Meilisearch required, Horizon
`staging` supervisors, `APP_DEBUG=false`, the production password policy).
Unlike production it may carry the labelled prototype demo dataset
(`COMPARO_DEMO_ACCOUNTS=true`) and is resettable (`migrate:fresh` allowed
there only). See `.env.staging.example` for the full env contract.

## Environments

| Env | Demo data | Debug | Search | Queue | Notes |
|---|---|---|---|---|---|
| local | opt-in | on | any Scout driver | `database` (no Docker) or `redis` | developer machine |
| testing | opt-in | on | `collection` (phpunit.xml) | `sync`/`database` | `php artisan test` |
| demo | opt-in | off | Meilisearch | redis | pre-sales / review environment |
| staging | on by default | off | Meilisearch (required) | redis (Horizon) | production-like, resettable |
| production | never (refused, `DemoDataRefused`) | off | Meilisearch (required) | redis (Horizon) | never resettable |

`DemoEnvironment::isAllowed()` / `DemoEnvironment::ALLOWED` gate which
environments accept `COMPARO_DEMO_ACCOUNTS=true`; `database/seeders/DatabaseSeeder.php`
refuses it outright in `production`.

## Required processes (staging & production)

| Process | Command | Why |
|---|---|---|
| Web (php-fpm behind nginx) | fpm pool + `tools/deploy/templates/nginx-site.conf` | serves `public/`, proxies PHP |
| Horizon | `php artisan horizon` | queue workers for `config/horizon.php` "staging"/"production" supervisors |
| Inertia SSR | `php artisan inertia:start-ssr` | server-rendered React pages |
| Scheduler | `php artisan schedule:work` (or cron `schedule:run` every minute — not both) | feed scheduling/reaping/pruning, search outbox drain (`routes/console.php`) |

`tools/deploy/templates/supervisor-comparo.conf` supervises Horizon, SSR and
the scheduler; the file's trailing comment gives the cron alternative.

## Required env keys (see `.env.staging.example` for the full, commented list)

`APP_KEY`, `APP_URL`, `DB_*` (pgsql), `REDIS_*`, `QUEUE_CONNECTION=redis` /
`QUEUE_LONG_CONNECTION=redis-long`, `SCOUT_DRIVER=meilisearch` +
`MEILISEARCH_HOST`/`MEILISEARCH_KEY`, `SESSION_SECURE_COOKIE`,
`COMPARO_DEMO_ACCOUNTS` / `COMPARO_DEMO_PASSWORD`, `FEATURE_*` flags (set
every one explicitly), `PASSKEYS_USER_HANDLE_SECRET`.

## (a) Local staging on Windows, without Docker

Uses `tools/staging/local-stack.mjs` (embedded-postgres + a Meilisearch
binary + `php artisan serve`/`inertia:start-ssr`/`queue:work`/`schedule:work`,
each auto-restarted like supervisord). Root: `C:\Users\medion\comparo-staging`.

```
C:\Users\medion\comparo-staging\
  app\                 the release: clean clone, composer --no-dev, built assets, .env
  runtime\pg\           npm dir with `embedded-postgres` (+ `pg`) installed
  runtime\meili\         the meilisearch.exe binary
  data\                 persistent Postgres + Meilisearch state (created on first start)
  logs\                 one log file per process
```

Build steps (PowerShell; run once, then `local-stack.mjs start` for later runs):

1. Clean clone into `app\`: `git clone <repo> app`
2. `cd app; composer install --no-dev --prefer-dist --optimize-autoloader`
3. `Copy-Item ..\..\COMPARO\.env.staging.example .env` then edit local
   overrides:
   - `QUEUE_CONNECTION=database`, `QUEUE_LONG_CONNECTION=database-long`
   - `CACHE_STORE=database`
   - `SESSION_SECURE_COOKIE=false`
   - `APP_URL=http://127.0.0.1:8080`
   - `DB_HOST=127.0.0.1`, `DB_PORT=55433`
   - `MEILISEARCH_HOST=http://127.0.0.1:7701`
4. `php artisan key:generate`
5. `npm ci`
6. `npm run build:ssr` (Wayfinder generates routes during this build — the
   app must already boot, i.e. `.env` with a real `APP_KEY` from step 4)
7. Set up `runtime\pg\` (`npm init -y; npm i embedded-postgres pg`) and
   `runtime\meili\` (place a Windows `meilisearch.exe` build there) once.
8. Start the stack: `node ..\..\COMPARO\tools\staging\local-stack.mjs start C:\Users\medion\comparo-staging`
9. In another shell, against the running stack:
   - `php artisan migrate --force`
   - `php artisan db:seed --force`
   - `php artisan comparo:search:sync-settings`
   - `php artisan comparo:search:reindex`
   - `php artisan optimize`
10. Restart the stack (Ctrl+C then start again) to pick up `optimize`'s
    cached config/routes.
11. Smoke test: `node tools\staging\smoke.mjs http://127.0.0.1:8080`

Stop with `node tools/staging/local-stack.mjs stop C:\Users\medion\comparo-staging`.

## (b) Linux server via `tools/deploy`

```
DEPLOY_ROOT=/var/www/comparo-staging GIT_REPO=<git-remote> \
  tools/deploy/deploy.sh <git-ref> [--maintenance]
```

Prerequisites (one-time, per `DEPLOY_ROOT`):

- `shared/.env` — copy `.env.staging.example`, fill every empty value from
  the secret store.
- `shared/storage/` — an existing Laravel `storage/` tree (or run one release
  by hand first to seed it), so uploads and logs persist across releases.
- nginx: `tools/deploy/templates/nginx-site.conf` (php-fpm 8.4, TLS, gzip,
  long cache for `/build/assets`, dotfiles denied, `client_max_body_size 110M`
  matching `comparo.feeds.max_payload_bytes`).
- supervisor: `tools/deploy/templates/supervisor-comparo.conf` (Horizon
  `stopwaitsecs=960` — exceeds the 900s long-queue job timeout — SSR,
  scheduler; cron alternative documented in the file).

`deploy.sh` does: fetch/checkout → link shared `.env`/`storage` → `composer
install --no-dev` → `npm ci` → `npm run build:ssr` → optional `artisan down
--secret` (only with `--maintenance`) → `migrate --force` → `db:seed
--class=RolesAndPermissionsSeeder --force` → `optimize` → `storage:link` →
atomic `current` symlink swap → `comparo:search:sync-settings` →
`horizon:terminate` / `inertia:stop-ssr` (supervisor restarts both against
the new `current`) → `up` (if `--maintenance`) → smoke test against
`APP_URL` from `shared/.env`. It runs no destructive database command and
keeps the last 5 releases (`KEEP_RELEASES`).

## (c) CI release-candidate workflow

`.github/workflows/staging.yml` ("Staging release candidate"), on push to
`main`/`phase-*` and `workflow_dispatch`. Builds exactly like `deploy.sh`
(`composer install --no-dev`, `npm ci`, `npm run build:ssr`) from
`.env.staging.example` with CI overrides (loopback `APP_URL`,
`SESSION_SECURE_COOKIE=false`, service-container DB/Redis/Meilisearch hosts,
a random `COMPARO_DEMO_PASSWORD` via `openssl rand`). Services: `postgres:16`,
`redis:7`, `meilisearch:v1.53.2` (`MEILI_ENV=production`, so a throwaway
≥16-byte master key is generated in the job, not stored as a secret).
`setup-php` 8.4 with `pdo_pgsql, redis, bcmath, intl, pcntl, posix`; Node 24.

After `migrate --force` + `db:seed --force` + `optimize` +
`comparo:search:sync-settings` + `comparo:search:reindex` (synchronous — the
command does not queue), it starts `php artisan serve`, `inertia:start-ssr`
and `horizon` in the background, waits for `/up`, asserts
`horizon:status` reports running, then runs
`node tools/staging/smoke.mjs http://127.0.0.1:8000` with
`SMOKE_EMAIL=merchant@comparo.example` (`DemoAccountsSeeder::MERCHANT_EMAIL`)
and `SMOKE_AFTER_LOGIN=/merchant/feeds`. `composer audit --no-dev` and
`npm audit --omit=dev --audit-level=high` run as separate,
`continue-on-error: true` steps (report only, never block). On failure,
`storage/logs/**` (including the CI process logs) is uploaded as an artifact.

## (d) Smoke test usage

```
node tools/staging/smoke.mjs <base-url> [--json]
# optional: SMOKE_EMAIL / SMOKE_PASSWORD / SMOKE_AFTER_LOGIN
```

Black-box HTTP checks: page loads, SSR marker present, guarded routes reject
anonymous access, a 404 page leaks no debug output, and (if credentials are
set) a login flow reaches `SMOKE_AFTER_LOGIN`. Every check is FAIL (exit 1)
or WARN (reported, doesn't fail the run — e.g. a slow response).

## (e) Rollback

`deploy.sh` never deletes the previous release until `KEEP_RELEASES` releases
later, and prints a rollback hint on every successful run and on failure
(via its `ERR` trap):

```
ln -sfn '<previous-release-path>' '<DEPLOY_ROOT>/current'
cd '<previous-release-path>' && php artisan storage:link && php artisan optimize \
  && php artisan horizon:terminate && php artisan inertia:stop-ssr
```

This re-points `current` and lets the supervisor restart Horizon/SSR against
the old release; it does not touch the database. Reverting a migration
requires a reviewed down-migration or a new corrective migration — never
infer that code rollback also reverts data (panel-operations.md, "Laravel
deployment sequence").

## (f) Known limitations

- `php artisan serve` is single-threaded (effectively one request at a time)
  on Windows — fine for `local-stack.mjs`/CI smoke checks, not a real load
  test.
- No Redis/Horizon in the Windows local stack: queues run on
  `QUEUE_CONNECTION=database` / `QUEUE_LONG_CONNECTION=database-long` workers
  (`php artisan queue:work`) instead, started by `local-stack.mjs`.
- No TLS locally — `local-stack.mjs` and the CI workflow both serve plain
  HTTP; `SESSION_SECURE_COOKIE=false` there only, never on the Linux
  staging/production nginx template.
- `.github/workflows/staging.yml` pins `actions/checkout` and `setup-php`/
  `setup-node` by commit SHA to match `tests.yml`; `actions/upload-artifact`
  is pinned by version tag instead because its SHA could not be verified
  offline in this session — replace with a verified SHA pin when convenient.
