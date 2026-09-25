# State management

All prototype state lives in `localStorage` under two keys:

| Key | Contents |
|---|---|
| `comparo.proto.v2` | the persisted slice of component state |
| `comparo.state.version` | schema version (currently **3**) |
| `comparo.proto.v2.corrupt` | written only when unreadable state was recovered |

Session identity for the event stream lives in `sessionStorage` under `comparo.sid`.

## What persists

Country and currency, session, accounts, saved, watch, alerts, notifications, votes, added reviews,
consent, admin overrides (`ov*`), extra coupons/audit/clicks/offers, theme, follows, community
content, lists, reputation, badges, feature flags, SEO overrides, merges, search log — plus this
iteration's basket, ranking weights, event stream, coupon votes, offer reports, alert targets, and
automation/anomaly/link/lead states.

## Versioning and migration

`load()` reads the version, runs `migrateState(state, from, to)` and writes the new version.
Migrations are additive and defensive: v1 → v2 converts a comma-joined `compare` string to an array;
v2 → v3 seeds the intelligence-layer containers and drops a non-object `rankW`. A schema change must
add a migration step — never assume a shape.

## Corruption recovery

If the stored blob is missing, not an object, or fails to parse, it is copied to
`comparo.proto.v2.corrupt`, removed, replaced with safe defaults, and the user is told once via a
toast. The app never crashes on bad state.

## Reset

`/design-system` → **Reset demo data…** asks for confirmation and can reset everything or one scope:
account & saved, community content, intelligence overrides, admin overrides, SEO overrides. Storage
usage and schema version are shown next to it.

## Multi-tab

A `storage` listener detects writes from another tab and surfaces a debounced notice
("Updated in another tab — reload to pull those changes in") rather than silently diverging or
clobbering the other tab's work.

## Write behaviour

Writes are debounced by 220 ms and always write the whole persisted slice — acceptable at prototype
size, and the boundary where a real backend takes over (see ARCHITECTURE.md).
