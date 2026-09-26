# Audit připravenosti — Comparo Performance (2026-09-26)

Branch `phase-4/reviews-orders`. Audit: rozsah, bezpečnost, provoz, kvalita (4 nezávislé audity) +
vlastní ověření klíčových tvrzení + běžící staging. Backlog: `docs/autonomy/BACKLOG.md` F-20…F-45.

## Verdikt

| Oblast | Stav |
|---|---|
| Staging (lokální, production-like) | **BĚŽÍ** — http://127.0.0.1:8080, smoke 26 kontrol / 0 FAIL |
| Staging (hostovaný, URL pro tým) | **BLOKOVÁNO člověkem** — server, doména, TLS, secrets |
| Demo pilot (fiktivní data prototypu) | připraven na stagingu |
| Pilot s reálnými obchody a nakupujícími | **NE** — chybí cesta pro reálná data (F-21…F-26) + 2FA (F-20) + zálohy/monitoring (F-27/28) + právní stránky (F-39) |
| Produkce | NE (Fáze 13 + lidské podpisy ADR-0018) |

## 1. Staging

| Položka | Stav | Důkaz |
|---|---|---|
| Definice prostředí `staging` (A-39) | HOTOVO | production-like: Meilisearch povinný, Horizon supervisory pro všechny fronty, produkční politika hesel, APP_DEBUG off; demo data jen opt-in a označená; `6e00460` |
| Lokální staging (Windows bez Dockeru) | BĚŽÍ | čistý klon → `composer --no-dev` → `npm ci` → `build:ssr` → PostgreSQL 18 (embedded) + Meilisearch 1.53.2 + web + SSR + 2 queue workery + scheduler; `tools/staging/local-stack.mjs`, root `C:\Users\medion\comparo-staging` |
| Smoke test | 26 kontrol, 0 FAIL | SSR na všech veřejných stránkách, API, ochrana merchant/admin/Horizon, 404 bez debug výpisu, assety, login merchanta → `/merchant/feeds`; odezvy 0,2–0,7 s (single-thread `artisan serve`) |
| Prohlížeč | OK | produktová stránka renderuje, 0 chyb v konzoli |
| Linux deploy kit | HOTOVO, neověřeno na serveru | `tools/deploy/deploy.sh` (releases/shared/current, atomický swap, rollback), nginx + supervisor šablony; `dfdce90` |
| CI „Staging release candidate“ | HOTOVO, první běh po push | `.github/workflows/staging.yml`: no-dev build, PG16 + Redis 7 + Meilisearch, migrate/seed/optimize/reindex, serve + SSR + Horizon, smoke s loginem, composer/npm audit |
| Runbook | HOTOVO | `docs/operations/staging.md` |

Omezení lokálního stagingu: bez Redis/Horizon (database fronty; Horizon ověřuje CI), bez TLS,
single-thread web server, dostupný jen z tohoto PC.

## 2. Co je hotové

| Fáze | Stav | Poznámka |
|---|---|---|
| 0 Základ | HOTOVO | Fortify (2FA, passkeys), RBAC, audit log, feature flags, CI (SQLite + PG16 + Meilisearch + frontend) |
| 1 Katalog, nabídky, landed price, compliance, ComparoRank | HOTOVO na demo datech | parita s prototypem ověřena (landed price 7 209, rank 3 239 případů) |
| 2 Feedy + párování | HOTOVO | merchant portál feedy/párování, staff fronta párování, parita engine |
| 3 Vyhledávání | HOTOVO | Meilisearch ověřen proti reálnému serveru (48/48), analytika bez IP |
| 4 Recenze, ověření nákupu, objednávky | ~25 % | schéma + čisté enginy s přesnou paritou (P4-00/02/03/04); chybí akce, UI, notifikace (P4-05…12) |
| 5–13 | NEZAČATO / částečně | affiliate `/go`, účty, zbytek merchant portálu, staff konzole, komunita, commercial, growth, SEO, hardening |

Pokrytí rout prototypu: 10/70 plně, 2 částečně, ~58 chybí (83 %). Aplikace má 58 rout.

Kvalita: 2 112 Pest testů (0 selhání), Larastan L7 0 chyb, Pint čistý, žádný soubor > 800 řádků,
0 TODO/FIXME, invarianty 1, 4, 5 vynucené architektonickými testy.

## 3. Co chybí — blokery pilotu s reálnými daty (ověřeno v kódu)

| # | Mezera | Backlog | Náročnost |
|---|---|---|---|
| 1 | Žádná produkční cesta pro referenční data (měny, země/trhy, kategorie) ani první staff účet — zapisuje je jen demo importer | F-21 | M |
| 2 | `market_price_stats` plní jen demo importer → reálné produkty bez historie cen a mediánů | F-22 | S–M |
| 3 | Zóny dopravy reálných obchodů se nikdy nezapíšou → jejich nabídky se v trzích nezobrazí | F-23 | M |
| 4 | Chybí správa compliance pravidel → reálné produkty `unknown` = bez nákupního odkazu | F-24 | M |
| 5 | Kurzy měn jsou demo (chybí ECB import) | F-25 | S |
| 6 | Chybí onboarding/provisioning obchodů (vytvoření, pozvánky, schválení) | F-26 | M |
| 7 | 2FA není vynucené pro staff, Horizon, vlastníky obchodů | F-20 | M |
| 8 | Zálohy + test obnovy; error tracking + alerting | F-27, F-28 | M (+ výběr dodavatele) |
| 9 | Právní stránky (GDPR, imprint, podmínky, cookies), sitemap/robots; export/výmaz dat | F-39, F-32 | M |
| 10 | Žádné frontend/E2E testy (33 stránek) | F-29 | L (schválení závislosti) |

## 4. Bezpečnost

0 CRITICAL. Silné stránky ověřené v kódu: izolace obchodů (404 pro cizí id), SSRF ochrana feedů
(DNS pinning, privátní rozsahy, ruční redirecty), XML bez DOCTYPE, šifrované přístupy k feedům,
parametrizované SQL, CSV bez formula injection, analytika bez IP, demo data v produkci odmítnuta.

| Nález | Závažnost | Stav |
|---|---|---|
| H-1 Podvržení Host hlavičky → útočníkův odkaz v e-mailu pro reset hesla | HIGH | OPRAVENO v tomto kroku |
| H-2 2FA nevynucené pro privilegované role | HIGH | backlog F-20 (před produkcí) |
| M-1 Chybí bezpečnostní hlavičky (nosniff, frame, referrer, HSTS, noindex mimo produkci) | MEDIUM | OPRAVENO (baseline); nonce CSP F-30 |
| M-3 Registrace / zapomenuté heslo / reset / potvrzení hesla bez throttlingu | MEDIUM | OPRAVENO |
| M-4 Enumerace e-mailů přes zapomenuté heslo | MEDIUM | OPRAVENO |
| M-5 Změna hesla neodhlásí ostatní zařízení | MEDIUM | OPRAVENO |
| A-31 Demo hodnocení v JSON-LD `AggregateRating` | HIGH (SEO/klamání) | OPRAVENO |
| M-6 Veřejné katalogové stránky bez limitu | MEDIUM | F-31 |
| M-7 / M-8 Výmaz účtu nechává účtenky; audit log drží IP bez retence | MEDIUM | F-32, F-33 |
| L-1 / L-2 Neověřené schéma URL webu obchodu; appearance cookie | LOW | OPRAVENO |
| L-3…L-10 tokeny, šifrování URL feedu, změna e-mailu, audit bezpečnostních událostí, passkey secret, CORS | LOW | F-40…F-45 |

## 5. Provoz

| Oblast | Stav |
|---|---|
| Build z čistého klonu, `optimize`, migrace na PostgreSQL | OVĚŘENO (staging) |
| Procesy: web, SSR, Horizon/fronty, scheduler, Meilisearch | OVĚŘENO lokálně; Horizon v CI |
| Health check | jen `/up` (hluboký check F-35) |
| Logy | JSON na stderr ve staging šabloně; chybí sběr logů |
| Zálohy, monitoring, alerting | CHYBÍ (F-27, F-28) |
| Scheduler úklid (failed jobs, batches, tokeny, snapshot Horizonu) | CHYBÍ (F-34) |
| HTTPS/HSTS/proxy | šablona nginx + HSTS v middleware; `TRUSTED_PROXIES` nutno nastavit |

## 6. Oponentura — co audit neověřil

- Hostovaný staging neexistuje: deploy kit prošel jen `bash -n`, na Linuxu ho ověří až CI workflow.
- Lokální staging nemá Redis/Horizon ani TLS; výkon neodpovídá produkci (single-thread server).
- Pokrytí testy je měřeno počtem testů, ne coverage nástrojem (není nainstalován pcov/xdebug).
- Přístupnost: jen kontrola 3 stránek v kódu, bez axe/Lighthouse.
- Controllers se testují přes routy; úplný diff routa → test neexistuje (F-37 souvisí).
- Tvrzení „reálná data nemají cestu do systému“ ověřeno grepem zapisovačů (jen importer).

## 7. Doporučené pořadí

1. **Hostovaný staging** (člověk: server/VPS nebo panel, doména, TLS, DB/Redis/Meilisearch secrets) → `tools/deploy/deploy.sh`.
2. **Cesta reálných dat** F-21 → F-24 → F-22 → F-23 → F-25 → F-26 (bez nich nelze pilot s obchody).
3. **Bezpečnost před pilotem**: F-20 (2FA), F-31, F-30.
4. **Provoz**: F-27 zálohy, F-28 monitoring (výběr dodavatele), F-34, F-35.
5. **Fáze 4 vlna 2** (P4-05 recenze, P4-06 ověření + objednávky), potom Fáze 5 `/go`.
6. **Právní minimum** F-39, F-32 + podpisy DPO/právník (ADR-0018).

## 8. Lidská rozhodnutí a vstupy

| Vstup | Proč |
|---|---|
| Hosting stagingu/produkce (vlastní server / aaPanel / cloud), doména, TLS | bez toho není veřejná URL |
| Dodavatel error trackingu (Sentry/Nightwatch) a e-mailu (Postmark/SMTP) | nová závislost / účet |
| Cíl záloh (off-site úložiště) | F-27 |
| Schválení frontend test frameworku (Pest browser / Playwright) | F-29 |
| Právní a DPO podpisy (ADR-0018: retence D-09, analytika, recenze) | před produkcí |
