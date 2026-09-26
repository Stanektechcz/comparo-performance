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
| `Compliance\Events\ComplianceRuleChanged` | closures in `AppServiceProvider` on `ProductComplianceRule` created/updated/deleted (`updated` fires only when dirty) | `Search\Indexing\Listeners\EnqueueProductDocuments` (priority re-index), `Search\Listeners\InvalidateCachedSuggestions` | sync (after commit) | Re-index the product immediately (a blocked product must leave results without waiting for the indexing backlog) and drop its cached header-suggestion entries |
| `Offers\Events\OfferPublished` / `OfferDeactivated` / `OfferRelinked`, `Pricing\Events\PriceChanged` | (as above) | *also* `Search\Indexing\Listeners\EnqueueProductDocuments` (`registerDomainListeners`, in addition to `BumpProductCacheVersion` above) | sync (after commit) | Queue the product('s two ids for a relink) onto `search_index_outbox` for the next `ProcessSearchOutbox` run |
| `Search\Events\SearchPerformed` | `Search\Analytics\PublishSearchRecorded` (called synchronously by `RecordSearch` right after the `search_queries` row is written; prefetch requests skipped) | — (no listener registered yet) | sync | Reserved for future consumers (e.g. Growth OS); the durable analytics row is already written before this fires |
| `Search\Events\ZeroResultSearchRecorded` | `Search\Analytics\PublishSearchRecorded`, only when `result_count = 0` | — (no listener registered yet) | sync | Reserved for future consumers |
| `Search\Events\SearchResultClicked` | `Search\Analytics\RecordSearchClick`, only for a first accepted click on a (search, entity) pair | — (no listener registered yet) | sync | Reserved for future consumers |

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
- The three search-analytics events (`SearchPerformed`, `ZeroResultSearchRecorded`, `SearchResultClicked`)
  are likewise dispatched with no consumer today; unlike the feed events they are not a gap, since the
  analytics row (`search_queries`/`search_clicks`) they describe is already written synchronously before
  they fire (see `docs/architecture/phase-3-search.md` §6).
- `EnqueueProductDocuments` (queues rows onto `search_index_outbox`) listens to five events —
  `OfferPublished`, `OfferDeactivated`, `OfferRelinked`, `PriceChanged` and `ComplianceRuleChanged` — the
  last one priority so a compliance change is visible in search without waiting for the outbox backlog.
