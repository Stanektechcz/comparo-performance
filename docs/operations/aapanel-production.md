# Spuštění na aaPanelu (staging i produkce)

Runbook pro jeden Linux server s aaPanelem. Staví na `tools/deploy/deploy.sh`,
`tools/deploy/templates/*` a `docs/operations/staging.md`. Hodnoty v `<...>` doplňte;
hesla a klíče ukládejte jen do `shared/.env` na serveru, nikdy do gitu ani do chatu.

## 0. Než začnete: co dnes funguje

| Režim | `APP_ENV` | Obsah | Stav |
|---|---|---|---|
| **A. Veřejný staging / demo** | `staging` | označená demo data prototypu, `noindex` | spustitelné hned |
| **B. Produkce** | `production` | jen reálná data (demo data produkce odmítá) | nasaditelné, ale bez obsahu, dokud nejsou hotové F-21 (referenční data + první staff účet), F-24, F-22, F-23, F-25, F-26 |

Před ostrou produkcí navíc: F-20 (povinné 2FA), F-27 (zálohy + test obnovy), F-28 (monitoring),
F-39 (právní stránky), podpisy DPO/právníka (ADR-0018). Detail: `docs/audit/2026-09-26-readiness-audit.md`.

Postup je pro oba režimy stejný; liší se jen `shared/.env` (krok 7).

## 1. Server

- Ubuntu 22.04/24.04 LTS, min. 2 vCPU / 4 GB RAM / 40 GB SSD, aaPanel (aktuální stabilní).
- DNS: A/AAAA záznam domény na server. Firewall (aaPanel → Security): povolit jen 22, 80, 443
  a port panelu; **PostgreSQL 5432, Redis 6379 a Meilisearch 7700 nepovolovat** (jen localhost).

## 2. Software z App Store

| Aplikace | Verze | Poznámka |
|---|---|---|
| Nginx | 1.24+ | |
| PHP | 8.4 | viz krok 3 |
| PostgreSQL Manager | PostgreSQL 16 | |
| Redis | 7.x | nastavit heslo (`requirepass`), bind 127.0.0.1 |
| Supervisor Manager | aktuální | Horizon, SSR, Meilisearch |
| Node.js version manager | Node 24 | build assetů a SSR server |

Composer: `curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer`.
aaPanel → Website → **PHP CLI version = 8.4** (deploy skript volá `php` z PATH).

## 3. PHP 8.4

aaPanel → App Store → PHP 8.4 → Setting:

1. **Install extensions:** `pgsql` + `pdo_pgsql`, `redis`, `intl`, `bcmath`, `fileinfo`, `opcache`, `exif`.
2. **Disabled functions — odebrat:** `putenv`, `proc_open`, `proc_get_status`, `pcntl_signal`,
   `pcntl_alarm`, `pcntl_async_signals`, `pcntl_signal_dispatch`, `pcntl_fork`, `pcntl_waitpid`,
   `pcntl_wexitstatus`, `exec`, `shell_exec` (Laravel `.env`, Composer, Horizon, SSR).
3. **Configuration:** `upload_max_filesize = 110M`, `post_max_size = 110M`, `memory_limit = 512M`,
   `max_execution_time = 120`.
4. Kontrola: `php -v` (8.4) a `php -m | grep -Ei 'pcntl|posix|pdo_pgsql|redis|intl|bcmath'` — všech šest.

## 4. Databáze, Redis, Meilisearch

**PostgreSQL** (PostgreSQL Manager nebo `sudo -u postgres psql`):

```sql
CREATE USER comparo WITH PASSWORD '<db-heslo>';
CREATE DATABASE comparo OWNER comparo ENCODING 'UTF8' LC_COLLATE 'C.UTF-8' LC_CTYPE 'C.UTF-8' TEMPLATE template0;
```

**Redis:** v aaPanelu nastavit heslo; ověřit `redis-cli -a '<redis-heslo>' ping` → `PONG`.

**Meilisearch** (není v App Store):

```bash
mkdir -p /opt/meilisearch/data && cd /opt/meilisearch
curl -L -o meilisearch https://github.com/meilisearch/meilisearch/releases/download/v1.53.2/meilisearch-linux-amd64
chmod +x meilisearch && chown -R www:www /opt/meilisearch
openssl rand -hex 32   # = MEILISEARCH master key → shared/.env (MEILISEARCH_KEY)
```

Supervisor Manager → Add daemon: name `comparo-meilisearch`, user `www`, run dir `/opt/meilisearch`,
command `/opt/meilisearch/meilisearch --db-path /opt/meilisearch/data --http-addr 127.0.0.1:7700 --master-key <master-key> --env production --no-analytics`.
Ověřit `curl -s http://127.0.0.1:7700/health` → `{"status":"available"}`.

## 5. Web v aaPanelu

1. Website → Add site: doména, PHP 8.4, bez FTP a bez MySQL, adresář `/www/wwwroot/comparo`.
2. Site → **Site directory:** vypnout **Anti-XSS attack (open_basedir)** (releases jsou za symlinkem).
   Po prvním nasazení (krok 8) nastavit Site directory na `/www/wwwroot/comparo/current`
   a **Running directory** na `/public`.
3. Site → **URL rewrite:** `laravel5`.
4. Site → **SSL:** Let's Encrypt → Force HTTPS.
5. Site → **Config** (do `server { }`, nic dalšího z panelové konfigurace nemazat):

```nginx
client_max_body_size 110m;
gzip on;
gzip_types text/css application/javascript application/json image/svg+xml;
location ^~ /build/assets/ { expires 1y; add_header Cache-Control "public, immutable"; access_log off; }
location ~ /\.(?!well-known) { deny all; }
```

Bezpečnostní hlavičky (HSTS, nosniff, frame, referrer, CSP baseline, noindex mimo produkci)
posílá aplikace sama (`SecurityHeaders` middleware) — v nginx je nezdvojovat.

## 6. Adresáře a přístup ke GitHubu (repo je privátní)

```bash
mkdir -p /www/wwwroot/comparo/{releases,shared/storage} && chown -R www:www /www/wwwroot/comparo
mkdir -p /home/www/.ssh && chown www:www /home/www/.ssh
sudo -u www ssh-keygen -t ed25519 -N '' -f /home/www/.ssh/comparo_deploy
cat /home/www/.ssh/comparo_deploy.pub
```

GitHub → repo → Settings → Deploy keys → Add (read-only) s tímto veřejným klíčem. Pak:

```bash
printf 'Host github.com\n  IdentityFile /home/www/.ssh/comparo_deploy\n  IdentitiesOnly yes\n' | sudo -u www tee /home/www/.ssh/config
sudo -u www ssh -o StrictHostKeyChecking=accept-new -T git@github.com   # "successfully authenticated"
sudo -u www git clone git@github.com:Stanektechcz/comparo-performance.git /www/wwwroot/comparo/deployer
```

`deployer/` slouží jen ke spouštění deploy skriptu (před každým nasazením `git pull`).

## 7. `shared/.env`

```bash
sudo -u www cp /www/wwwroot/comparo/deployer/.env.staging.example /www/wwwroot/comparo/shared/.env
sudo -u www chmod 600 /www/wwwroot/comparo/shared/.env
echo "base64:$(openssl rand -base64 32)"   # = APP_KEY
```

Upravit (ostatní hodnoty šablony ponechat):

| Klíč | A. staging/demo | B. produkce |
|---|---|---|
| `APP_ENV` | `staging` | `production` |
| `APP_NAME` | `"Comparo Performance (staging)"` | `"Comparo Performance"` |
| `APP_URL` | `https://<staging-domena>` | `https://<domena>` |
| `APP_KEY` | vygenerovaný | vygenerovaný (jiný než staging) |
| `LOG_CHANNEL` | `daily` | `daily` |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | z kroku 4 | z kroku 4 |
| `REDIS_PASSWORD` | z kroku 4 | z kroku 4 |
| `REDIS_PREFIX` / `CACHE_PREFIX` / `SCOUT_PREFIX` | `comparo_staging_` / `comparo_staging_cache_` / `staging_` | `comparo_` / `comparo_cache_` / `prod_` |
| `MEILISEARCH_KEY` | master key z kroku 4 | master key z kroku 4 |
| `COMPARO_DEMO_ACCOUNTS` | `true` | `false` |
| `COMPARO_DEMO_PASSWORD` | silné heslo (nebo prázdné = náhodné, vypíše se jednou) | — |
| `MAIL_*` | SMTP sandbox | produkční SMTP |
| `PASSKEYS_USER_HANDLE_SECRET` | `openssl rand -hex 32` | `openssl rand -hex 32` |
| `TRUSTED_PROXIES` | prázdné; za Cloudflare/LB jejich IP rozsahy | totéž |

Staging a produkce na jednom serveru: oddělená databáze, prefixy Redis/Scout a jiný `APP_KEY`.

## 8. První nasazení

```bash
cd /www/wwwroot/comparo/deployer && sudo -u www git pull
sudo -u www env DEPLOY_ROOT=/www/wwwroot/comparo \
  GIT_REPO=git@github.com:Stanektechcz/comparo-performance.git \
  RUN_SMOKE_TEST=false \
  bash tools/deploy/deploy.sh main
```

Skript: klon release → `composer install --no-dev` → `npm ci` → `npm run build:ssr` → `migrate --force`
→ role a oprávnění → `optimize` → přepnutí `current` → synchronizace nastavení vyhledávání → restart Horizon/SSR.
Pak (jen poprvé):

```bash
cd /www/wwwroot/comparo/current
sudo -u www php artisan db:seed --force                 # A: demo data + demo účty; B: jen role
sudo -u www php artisan comparo:search:reindex
```

Teď dokončit krok 5.2 (Site directory `.../current`, Running directory `/public`).

## 9. Procesy na pozadí

Supervisor Manager → Add daemon (user `www`, run dir `/www/wwwroot/comparo/current`):

| Název | Příkaz |
|---|---|
| `comparo-horizon` | `php /www/wwwroot/comparo/current/artisan horizon` |
| `comparo-ssr` | `php /www/wwwroot/comparo/current/artisan inertia:start-ssr` |

U `comparo-horizon` doplnit v konfiguraci programu (Supervisor Manager → konfigurační soubor)
`stopwaitsecs=960` a `stopsignal=TERM`, aby se neukončily běžící importy feedů (timeout 900 s).

aaPanel → **Cron** → Add task: Shell script, každou minutu:

```bash
cd /www/wwwroot/comparo/current && sudo -u www php artisan schedule:run >> /dev/null 2>&1
```

Kontrola: `sudo -u www php artisan horizon:status` → `running`.

## 10. Ověření

```bash
cd /www/wwwroot/comparo/current
node tools/staging/smoke.mjs https://<domena>
SMOKE_EMAIL=merchant@comparo.example SMOKE_PASSWORD='<demo-heslo>' SMOKE_AFTER_LOGIN=/merchant/feeds \
  node tools/staging/smoke.mjs https://<domena>     # jen A (demo účty)
```

Očekávání: 26 kontrol, 0 FAIL (na HTTPS navíc HSTS a Secure cookie). V produkci (B) bez obsahu
selže kontrola produktové stránky — to je známý stav do dokončení F-21…F-26.

## 11. Další nasazení a rollback

```bash
cd /www/wwwroot/comparo/deployer && sudo -u www git pull
sudo -u www env DEPLOY_ROOT=/www/wwwroot/comparo GIT_REPO=git@github.com:Stanektechcz/comparo-performance.git \
  bash tools/deploy/deploy.sh <tag-nebo-commit> [--maintenance]
```

Rollback kódu (migrace se nevracejí automaticky — před nasazením je zkontrolovat):

```bash
cd /www/wwwroot/comparo && ls -1 releases                    # vybrat předchozí release
sudo -u www ln -sfn releases/<predchozi> current
sudo -u www php current/artisan optimize && sudo -u www php current/artisan horizon:terminate
sudo -u www php current/artisan inertia:stop-ssr               # Supervisor SSR znovu spustí
```

## 12. Zálohy (minimum, dokud není F-27)

aaPanel → Cron → Shell script, denně 02:30:

```bash
mkdir -p /www/backup/comparo && sudo -u postgres pg_dump -Fc comparo > /www/backup/comparo/comparo-$(date +\%F).dump && find /www/backup/comparo -name '*.dump' -mtime +14 -delete
```

Plus kopie `shared/.env` a `shared/storage/app` mimo server. Meilisearch se po ztrátě obnoví
příkazem `php artisan comparo:search:reindex`. Obnovu jednou vyzkoušet na jiném serveru.
