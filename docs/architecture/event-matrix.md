# Event matrix

Ground truth: `php artisan event:list` (2026-09-25) filtered to application (non-vendor, non-Horizon,
non-Eloquent-hook) events, cross-checked against `App\Domain\**\Events`. See
[ADR-0015](../adr/0015-domain-events-feature-flags-merchant-context.md) for the design rationale.

## Domain events

| Event | Producer | Listener(s) | Sync/queued | Purpose |
|---|---|---|---|---|
| `Offers\Events\OfferPublished` | `Offers\Actions\PublishOffer` | `Platform\Listeners\BumpProductCacheVersion` | sync (after commit) | Invalidate cached comparisons for the product a new/changed offer belongs to |
| `Offers\Events\OfferDeactivated` | `Offers\Actions\DeactivateOffer` (incl. `Feeds\Actions\ReconcileMissingListings`) | `Platform\Listeners\BumpProductCacheVersion` | sync (after commit) | Same, when an offer is deactivated (feed reconciliation, staff/merchant action) |
| `Offers\Events\OfferRelinked` | `Offers\Actions\LinkListing` (manual/staff relink to a different product) | `Platform\Listeners\BumpProductCacheVersion` (bumps **both** previous and new product) | sync (after commit) | Keep both the old and new product's cached comparisons correct |
| `Pricing\Events\PriceChanged` | `Pricing\Actions\RecordPriceSnapshot` | `Platform\Listeners\BumpProductCacheVersion` | sync (after commit) | Invalidate cache on a real price/currency change (carries old/new minor units, currency, `SnapshotReason`) |
| `Matching\Events\ProductMatched` | `Matching\Actions\MatchListing` / `DecideMatch` | `Feeds\Listeners\PublishLatestObservation` | **queued** (`ShouldQueue`) | Publish the listing's current observation once a match decision is written |
| `Feeds\Events\FeedImported` | `Feeds\Jobs\FinalizeFeedRun` (run completed as `published`/`published_with_warnings`/`unchanged`) | — (no listener registered yet) | after commit | Reserved for merchant notifications (not implemented) |
| `Feeds\Events\FeedFailed` | `Feeds\Jobs\FinalizeFeedRun` (run failed) | — (no listener registered yet) | after commit | Reserved for merchant/staff alerting (not implemented) |

## Framework and infrastructure events (for completeness, not Phase 2 scope)

| Event | Listener | Purpose |
|---|---|---|
| `Illuminate\Auth\Events\Registered` | `SendEmailVerificationNotification` | Fortify registration flow |
| `eloquent.saved`/`eloquent.deleted`: `Offer`, `ProductComplianceRule`, `Coupon`, `MerchantShippingZone`, `MerchantRiskEvent`, `Country` | closures in `AppServiceProvider` | Cache-version safety net (see ADR-0015 "two paths, one budget") and prototype-import bookkeeping |
| `Laravel\Horizon\Events\*` | Horizon's own listeners | Queue monitoring dashboard |

## Notes

- All Phase 2 domain events are `final readonly`, carry ids and scalars only (never Eloquent models),
  and implement `ShouldDispatchAfterCommit` so a listener never observes a row from a transaction that
  could still roll back.
- `Matching\Events\ProductMatched` is the only queued listener in this list; every other domain-event
  listener is synchronous because it must be visible on the very next page view (see ADR-0015).
- `FeedImported`/`FeedFailed` are dispatched today but have no consumer — notifications are a known gap
  (see `docs/implementation-status.md`).
