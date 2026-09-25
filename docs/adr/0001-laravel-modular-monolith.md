# ADR-0001: Laravel modular monolith

- Status: Accepted
- Date: 2026-09-25
- Related: C-01, ADR-0004, ADR-0010, [target-laravel-architecture.md](../architecture/target-laravel-architecture.md)

## Context

The prototype (`Comparo Performance.dc.html`, `seed*.js`, `intel.js`, `growth.js`, `commercial.js` and
the other engine files at the repository root) is a single-page browser application whose scoring
engines are pure functions over a seed graph. Root docs disagree on the target stack: `ARCHITECTURE.md`
describes Next.js, `BACKEND-MIGRATION.md` Laravel 11 with Vue or an SPA (C-01). One team maintains
the product; five surfaces (consumer, merchant, staff, Growth OS, Commercial OS) share one data graph.

## Decision

1. **Stack.** Laravel 13.33 on PHP 8.4 (supported range 8.3–8.5; `composer.json` requires `^8.3`),
   built from the official React starter kit:

   | Concern | Choice |
   |---|---|
   | Web UI | Inertia 3 + React 19 + TypeScript, SSR enabled (`config/inertia.php`) |
   | Styling / build | Tailwind 4, Vite+ (`vp`, `vite-plus`), React Compiler |
   | Auth | Fortify: registration, login, email verification, password reset, 2FA, passkeys |
   | Typed routes | Wayfinder (`@laravel/vite-plugin-wayfinder`) |
   | API tokens | Sanctum |
   | Authorization | spatie/laravel-permission 8 (staff), policies (merchants) |
   | Search | Scout 11 + meilisearch-php |
   | Queues | Horizon 5 on Redis (predis client) |
   | Files | Flysystem S3 (MinIO locally) |
   | Quality | Pest 4.7, Pint, Larastan (level 7) |
   | Agent tooling | Laravel Boost 2.10 (`AGENTS.md`, `.mcp.json`, `.claude/skills`) |

   `composer.json` `config.platform` declares `ext-pcntl` and `ext-posix` so Horizon installs on
   Windows development machines; Horizon itself only runs on Linux.

2. **One deployable, bounded contexts inside it.**
   - Eloquent models live in `app/Models` (thin; Laravel convention so factories and relations
     resolve without configuration).
   - Domain logic lives in `app/Domain/{Context}/{Capability}` — for example
     `app/Domain/Pricing/LandedPrice/LandedPriceCalculator.php`.
   - Pure scoring services take immutable (`readonly`) input DTOs and the evaluation time as
     arguments. They never touch the database, the container or the clock, and they are unit tested.
   - Query objects (`app/Domain/*/Queries`, e.g. `app/Domain/Offers/Queries/ProductOfferComparison.php`)
     load data and build the DTO contexts.
   - HTTP is thin controllers plus explicit presenters (`app/Http/Presenters`) / API resources:
     camelCase Inertia props, snake_case public API.
3. **The prototype stays at the repository root, untouched**, as the behaviour specification until an
   explicit cleanup release. It is excluded from lint and format (`prototypeSpecification` list in
   `vite.config.ts`). SHA-256 integrity of the prototype files was verified after every migration step.
4. Internal service endpoints described in `ARCHITECTURE.md` (`/internal/rank`, `/internal/match`)
   become in-process classes, not HTTP calls.

## Consequences

- One repository, one CI pipeline, one database; transactions can span contexts where needed.
- Context boundaries are enforced by convention, code review and architecture tests
  (`tests/Architecture/{PureServicesTest,RankingPurityTest,AppendOnlyHistoryTest}.php`), not by package boundaries.
- Scoring services are portable to queued recomputation without change because they are pure.
- The prototype files must never be edited by tooling; `vite.config.ts` and `CLAUDE.md` both say so.
- `CLAUDE.md` refers to `app/Domain/*/Services`; the implemented layout is
  `app/Domain/{Context}/{Capability}`. The capability folder is authoritative.

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| Next.js front end + Laravel API (ARCHITECTURE.md) | Two runtimes, duplicated auth and SEO rendering, no team benefit |
| Microservices per engine | Engines are pure functions over one graph; network boundaries add latency and consistency cost |
| Domain models in `app/Domain/*/Models` | Breaks factory/relationship conventions for no isolation gain; models stay thin anyway |
| Keep the SPA and serve JSON | Loses SSR for SEO-critical pages and server-side compliance gating |
