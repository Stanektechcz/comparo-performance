# Development setup

Two local profiles (ADR-0011): **SQLite** (no Docker; the reference machine) and **Docker services**
(`compose.yaml`). The Laravel app always runs on the host.

## 1. Prerequisites

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.4 (8.3–8.5 supported) | Extensions: `pdo_sqlite` (SQLite profile) or `pdo_pgsql` (PostgreSQL), `intl`, `mbstring`, `openssl`, `fileinfo`, `curl`. `pcntl`/`posix` are not needed on Windows (declared in `composer.json` `config.platform`) |
| Composer | 2.x | |
| Node.js | 22 (as CI) | npm ships with it |
| Docker | optional | Only for the Docker profile |

On the reference Windows machine PHP lives at `C:\php` and is **not on PATH**. Put it on PATH for the
current shell before any command below:

```bash
# Git Bash
export PATH="/c/php:$PATH"
```

```powershell
# PowerShell
$env:Path = "C:\php;$env:Path"
```

## 2. Install

```bash
composer install
npm ci
cp .env.example .env            # PowerShell: Copy-Item .env.example .env
php artisan key:generate
```

## 3a. SQLite profile (no Docker)

Edit `.env` as its comments describe:

```dotenv
DB_CONNECTION=sqlite
# remove or comment DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
QUEUE_CONNECTION=database
CACHE_STORE=database
SCOUT_DRIVER=database
MAIL_MAILER=log
```

Create the database file and migrate:

```bash
touch database/database.sqlite                       # PowerShell: New-Item database/database.sqlite -ItemType File
php artisan migrate
```

## 3b. Docker profile (PostgreSQL 16, Redis 7, Meilisearch, MinIO, Mailpit)

Keep the `.env.example` defaults (`DB_CONNECTION=pgsql`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`,
`SCOUT_DRIVER=meilisearch`, Mailpit on 1025).

```bash
docker compose up -d
docker compose ps                # wait until pgsql and redis are healthy
php artisan migrate
```

| Service | Port | UI |
|---|---|---|
| PostgreSQL | 5432 (db/user/password `comparo`) | — |
| Redis | 6379 | — |
| Meilisearch | 7700 | http://127.0.0.1:7700 |
| MinIO | 9000 / 9001 | http://127.0.0.1:9001 (`comparo` / `comparo-secret`); create bucket `comparo-private` |
| Mailpit | 1025 SMTP | http://127.0.0.1:8025 |

## 4. Demo data

Demo data is the fictional prototype seed. It is imported only in `local`, `testing` or `demo`
environments, never in production.

```bash
# .env: COMPARO_DEMO_ACCOUNTS=true, optionally COMPARO_DEMO_PASSWORD=...
php artisan migrate:fresh --seed
```

| Persona | Email | Role |
|---|---|---|
| Shopper | `demo@comparo.example` | consumer |
| Merchant | `merchant@comparo.example` | merchant member |
| Staff | `admin@comparo.example` | staff (super-admin bundle) |

With `COMPARO_DEMO_PASSWORD` empty the seeder prints a random password once; existing demo accounts keep
their password unless `COMPARO_DEMO_PASSWORD` is set.

How it works: `DatabaseSeeder` always runs `RolesAndPermissionsSeeder`. When `COMPARO_DEMO_ACCOUNTS=true`
it runs `DemoDataSeeder`, which imports `database/data/prototype/seed-snapshot.json` through
`App\Domain\Platform\PrototypeImport\PrototypeSnapshotImporter` (all timestamps shifted so demo data is
fresh; idempotent) and creates the personas (`DemoAccountsSeeder`). In `production` it refuses
(`DemoDataRefused`); in other environments outside `local`/`testing`/`demo` it skips with a warning.

## 5. Run

```bash
composer run dev          # app server, queue listener, logs and Vite (php artisan dev)
```

| Task | Command |
|---|---|
| Production asset build | `npm run build` |
| SSR bundle | `npm run build:ssr`, then `php artisan inertia:start-ssr` |
| Queue worker (Windows / no Horizon) | `php artisan queue:work --queue=default,feed-import,matching,pricing,search,analytics` |
| Horizon (Linux, WSL, Docker only) | `php artisan horizon` → `/staff/horizon` (permission `staff.horizon.view`) |

## 6. Tests and quality gates

| Check | Command |
|---|---|
| PHP tests (in-memory SQLite, see `phpunit.xml`) | `php artisan test --compact` |
| Parity tests only | `php artisan test --compact tests/Unit/Parity` |
| PHP style | `vendor/bin/pint --test` (PowerShell: `php vendor/bin/pint --test`) |
| Static analysis (Larastan level 7) | `vendor/bin/phpstan analyse` (PowerShell: `php vendor/bin/phpstan analyse`) |
| TypeScript | `npm run types:check` |
| JS/TS lint + format | `npm run check` |
| Everything CI runs | `composer ci:check` |

### Prototype integrity

`php artisan comparo:verify-prototype` fails when any file listed in `docs/prototype/SHA256SUMS`
is missing or changed. Git stores those files byte-for-byte (`-text` in `.gitattributes`).

### Verifying against PostgreSQL without Docker

CI runs the suite on PostgreSQL 16. Locally, without Docker or a system install:

1. In a scratch directory **outside the repository**: `npm install embedded-postgres pg`.
2. Start a throwaway server with UTF-8 databases. `initdb` inherits the Windows locale (for example
   WIN1250), so create the databases explicitly:
   `CREATE DATABASE comparo_test ENCODING 'UTF8' LC_COLLATE 'C' LC_CTYPE 'C' TEMPLATE template0`.
3. Run the suite with the bundled driver enabled (`pdo_pgsql` is present in `C:\php\ext` but disabled
   in `php.ini`):

```bash
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=comparo_test \
DB_USERNAME=comparo DB_PASSWORD=comparo php -d extension=pdo_pgsql artisan test --compact
```

4. Stop the server; if the Node wrapper is killed, stop orphaned `postgres.exe` children by PID
   after checking their command line points into the scratch directory.

### Long-running queues (feed import, matching, pricing)

Phase 2 feed jobs run up to 900s. They dispatch onto the `redis-long` / `database-long` connections
(config/queue.php), whose `retry_after` (`QUEUE_LONG_RETRY_AFTER`, default 960s) is kept above every
job's timeout so a worker never picks up a duplicate of a still-running job. `config('comparo.queues.long_running_connection')`
is what job classes read. `QUEUE_LONG_CONNECTION` wins; otherwise the default is derived from
`QUEUE_CONNECTION`:

| `QUEUE_CONNECTION` | long-running connection |
|---|---|
| `sync` | `sync` (tests: the pipeline runs inline, no extra setup) |
| `database` | `database-long` |
| `redis` | `redis-long` |
| anything else | the same connection — it must then have a long `retry_after` itself |

Never point `QUEUE_LONG_CONNECTION` at `database` or `redis`: their `retry_after` is 90s, so a 900s parse
would be delivered twice. `App\Domain\Platform\Queues\LongRunningQueue` checks this at boot: when the
chosen connection's `retry_after` is not greater than the longest feed job timeout
(`FeedRunPipeline::longestJobTimeout()`, 900s), it logs a critical message (console processes such as queue
workers) and, in `local`/`testing`, throws `LongRunningQueueMisconfigured` so every artisan command fails
until it is fixed. In production, Horizon runs three dedicated supervisors on `redis-long`
(config/horizon.php): `feed-import` (900s), `matching` (300s), `pricing` (300s), alongside the existing
`default` queue's supervisor.

## 7. Prototype parity fixtures

```bash
node tools/prototype-parity/export-fixtures.mjs          # regenerate fixtures + seed snapshot
node tools/prototype-parity/export-fixtures.mjs --check  # fail if anything would change (CI)
```

Outputs: `tests/Fixtures/PrototypeParity/*.json`, `database/data/prototype/seed-snapshot.json`.
The prototype files are read only. Never hand-edit fixtures (ADR-0010).

## 8. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `npm run build` / `vp dev` fails in the Wayfinder plugin (`php` not found, route generation error) | Wayfinder runs `php artisan` from Node; `php` is not on PATH | Put `C:\php` on PATH in the same shell (section 1) and rerun |
| `could not find driver` on `migrate` with `DB_CONNECTION=pgsql` | `pdo_pgsql` disabled in `php.ini` | Uncomment `extension=pdo_pgsql` (and `extension=pgsql`) in `C:\php\php.ini`, or run once with `php -d extension=pdo_pgsql artisan migrate`; or use the SQLite profile |
| `composer install` complains about `ext-pcntl` / `ext-posix` | Old lock or removed `config.platform` | Keep `config.platform` in `composer.json`; do not run Horizon on Windows |
| `Connection refused` on 6379 / 7700 | Redis/Meilisearch not running | Start Docker services or switch to the SQLite profile drivers |
| Update/delete on `price_snapshots` or `audit_logs` throws | Append-only triggers and `AppendOnly` guard (ADR-0003) | Insert a correcting row instead |
| Frontend change not visible | Assets not rebuilt | Run `npm run dev` or `composer run dev` |
