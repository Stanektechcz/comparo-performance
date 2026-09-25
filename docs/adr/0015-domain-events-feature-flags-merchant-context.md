# ADR-0015: Domain events, feature flags and merchant context

- Status: Accepted
- Date: 2026-09-25
- Related: A-17, `docs/architecture/phase-2-feeds-matching.md` §1, §6, §10; ADR-0005, ADR-0013

## Context

Phase 2 adds cross-cutting concerns that several modules need but none should own: telling the rest of
the system a price or offer changed, gating unfinished/risky features without a deploy, and knowing
which merchant a request is acting for. Each needed a decision on where it lives and how strict it is.

## Decision

### Domain events (after-commit)

Events are final, readonly, carry ids and scalars only (no models, no closures — safe to serialize),
and are dispatched with `ShouldDispatchAfterCommit` so a listener never observes a row that could still
roll back:

| Event | Producer | Listener(s) | Sync/queued |
|---|---|---|---|
| `Offers\Events\OfferPublished` | `Offers\Actions\PublishOffer` | `Platform\Listeners\BumpProductCacheVersion` | sync |
| `Offers\Events\OfferDeactivated` | `Offers\Actions\DeactivateOffer` (incl. reconciliation) | `BumpProductCacheVersion` | sync |
| `Offers\Events\OfferRelinked` | `Offers\Actions\LinkListing` (re-link to a different product) | `BumpProductCacheVersion` (bumps **both** the previous and new product) | sync |
| `Pricing\Events\PriceChanged` | `Pricing\Actions\RecordPriceSnapshot` (old/new minor units, currency, `SnapshotReason`) | `BumpProductCacheVersion` | sync |
| `Matching\Events\ProductMatched` | `Matching\Actions\MatchListing`/`DecideMatch` | `Feeds\Listeners\PublishLatestObservation` | **queued** (`ShouldQueue`) |
| `Feeds\Events\FeedImported` | `Feeds\Jobs\FinalizeFeedRun` (successful/unchanged run) | — (reserved for notifications) | — |
| `Feeds\Events\FeedFailed` | `Feeds\Jobs\FinalizeFeedRun` (failed run) | — (reserved for notifications) | — |

`Platform\Listeners\BumpProductCacheVersion` is registered **synchronously**, deliberately: because the
triggering events are already after-commit, a queued bump would still leave a real window where the
next page view serves a stale cached comparison. `Matching\Events\ProductMatched` is dispatched to a
queued listener instead, because publishing the resulting observation is not on the request's critical
path and `Feeds\Listeners\PublishLatestObservation` may itself do further work.

### Cache invalidation has two paths, one budget

1. **Events**, above — the primary, explicit path for the actions this phase introduces.
2. **A guarded Eloquent model hook** (`Offer::saved`/`Offer::deleted` in `AppServiceProvider`) — a
   safety net for any other code path that writes an `Offer` row directly (compliance rule changes,
   merchant risk events, coupons, shipping zones already used this pattern pre-Phase 2). The hook only
   bumps when the model **actually changed** (`wasRecentlyCreated || wasChanged()`), and always defers
   through `DB::afterCommit()` — never inline — so a rolled-back write never bumps a version. Both
   paths call the same `CatalogCacheVersion`, so a direct write and an action-driven write cannot
   produce two different bump semantics for the same table.

### Feature flags (A-17): config-backed, fail-closed

`App\Domain\Platform\Features\FeatureFlags` is the **sole reader** of `config('features.*')` — no call
site reads `config()` directly. `enabled(Feature $feature)` defaults to `true` when a key is missing
(`config($key, true)`), but every flag Phase 2 actually defines ships **on** in `config/features.php`
(`merchant-feeds`, `feed-url-fetch`, `matching-auto-publish`), each backed by an env var
(`FEATURE_MERCHANT_FEEDS`, `FEATURE_FEED_URL_FETCH`, `FEATURE_MATCHING_AUTO_PUBLISH`) so an operator can
flip one off without a deploy — this is what "fail-closed" means operationally here: turning a flag off
is a one-line env change, not a code path that silently degrades. The `feature:{name}` route middleware
(used on every `/merchant/*` route via `feature:merchant-feeds`) 404s the whole route group when its
flag is off, rather than rendering a broken partial page. `clientFlags()` exposes only flags marked
`clientVisible()` to the shared Inertia prop, so a server-only flag can never be read or inferred from
the client bundle. Pennant is deferred (config-backed flags are sufficient for Phase 2's three
flags); adopting it needs a dependency approval per the open decision it is recorded under.

### Merchant context (session-selected) and isolation

`ResolveMerchantContext` middleware binds one `MerchantContext` instance per request: it loads the
user's merchant memberships (ordered by id), picks the session's `merchant.active_id` if the user
still belongs to it, else the lowest-id membership, and aborts the whole request with 403 before any
`/merchant/*` route runs if the user has no membership at all. `merchant.context.update`
(`Merchant\MerchantContextController@update`) is the only way the active merchant changes, and it is
rate-limited (`throttle:30,1`).

**404-for-foreign-ids isolation:** every `/merchant/*` route resolves its `{feed}`/`{run}`/`{listing}`
as a plain integer (never implicit route-model binding) through a query that filters by the *active*
`MerchantContext::$merchantId` first (`Feeds\Queries\MerchantFeedSources`,
`Matching\Queries\MerchantMatchingQueue`, etc.) — a valid id belonging to a different merchant the user
also happens to belong to is a 404, not a 403, so a merchant can never learn that an id exists.
Authorization (`FeedSourcePolicy`, `MerchantProductPolicy`) then gates *what* the resolved resource's
owner may do (owner/manager mutate, analyst read-only, credentials owner-only) — isolation and
authorization are two separate checks, in that order, and negative tests cover both, including a user
who belongs to merchants A and B.

## Consequences

- Two cache-invalidation paths (events + model hook) must be kept in agreement whenever a new write
  path to `offers` is added; a new Offers action should prefer dispatching the matching event over
  relying on the model hook alone.
- Config-backed flags mean flag state is per-deploy/per-environment, not per-user or A/B-testable;
  acceptable for Phase 2's three operational kill-switches, revisit if product wants staged rollout.
- Session-selected merchant context means a user who is a member of several merchants sees one at a
  time; every merchant-scoped query must remember to filter by the active id — a query that forgets is
  a merchant-isolation bug, which is why `tests/Architecture` and negative tests exist for this boundary
  (ADR-0005).

## Alternatives considered

| Alternative | Rejected because |
|---|---|
| Queue `BumpProductCacheVersion` for all four events | Reopens the stale-comparison window after-commit events exist to close |
| Read `config('features.*')` directly at call sites | A-17 requires one seam so flags can later move to a DB-backed system (Pennant) without touching call sites |
| Implicit route-model binding for `{feed}`/`{run}`/`{listing}` | Laravel's default binding does not know about the active merchant and would either leak existence (403 on a real id) or need a global scope hack; an explicit merchant-scoped query is simpler to audit |
| 403 instead of 404 for a foreign-merchant id | 403 confirms the id exists; 404 does not (matches ADR-0005's isolation stance) |
