@AGENTS.md

# Comparo Performance — project rules

Laravel 13 modular monolith (Inertia 3 + React 19 SSR) replacing the browser prototype.

## The prototype is the specification

- `Comparo Performance.dc.html`, `seed*.js`, the engine `.js` files, root `*.md` specs, `screenshots/`
  and `uploads/` are the **behaviour specification**. Never edit, move, format or delete them
  (until an explicit cleanup release). They are excluded from lint/format in `vite.config.ts`.
- Never read the 1.7 MB HTML whole — grep for functions and read ranges.
  Maps: `docs/architecture/current-prototype-map.md`, `scoring-engines-map.md`,
  `entity-inventory.md`, `design-system-map.md`.

## Invariants (see docs/adr/)

1. ComparoRank never reads commission, subscription/plan, commercial spend or campaign data.
   `tests/Architecture` enforces this.
2. Compliance is evaluated server-side before serialization. Blocked/unknown products never
   produce a purchasable offer in any response, cache entry, index or notification.
3. Money is integer minor units + ISO-4217 code (`App\Domain\Shared\Money`). No floats.
4. Price history (`price_snapshots`) is append-only. Corrections are new rows.
5. Scoring services in `app/Domain/{Context}/{Capability}` (e.g. `Offers/Ranking`, `Pricing/LandedPrice`)
   are pure: immutable input DTOs, typed results, no DB access, no clock reads (time is passed in).
   Data loading lives in `app/Domain/{Context}/Queries`; serialization in `app/Http/Presenters`.
6. Merchant isolation is enforced by policies + merchant-scoped queries, with negative tests.
7. Never fake completeness: unfinished features are marked incomplete, never toasted as done.

## Layout

- Eloquent models: `app/Models` (thin; no scoring). Domain logic: `app/Domain/{Context}`.
- Parity fixtures: `tools/prototype-parity/export-fixtures.mjs` → `tests/Fixtures/PrototypeParity/`.
- Status: `docs/implementation-status.md`. Decisions: `docs/adr/`, `docs/architecture/open-decisions.md`.

## Commands (PHP lives in C:\php on this machine — put it on PATH first)

- `php artisan test --compact` · `vendor/bin/pint --dirty --format agent` · `vendor/bin/phpstan analyse`
- `npm run types:check` · `npm run check` · `npm run build` (Wayfinder needs `php` on PATH)
- `node tools/prototype-parity/export-fixtures.mjs` regenerates golden fixtures.
