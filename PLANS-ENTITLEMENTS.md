# Plans & entitlements

## Entitlement engine

`FeatureEntitlement { feature, plan, enabled, limit, value, usage_period }`. Eighteen features
across nine groups (Profile, Feeds, Analytics, Reviews, Deals, Campaigns, API, Team, Support).
Nothing in the UI hard-codes plan names to capabilities — every gate asks the engine.

Examples: `analytics.history_days` (30 / 180 / 730 / 1825), `deal.active_limit` (3 / 10 / 30 / 100),
`api.requests_month` (0 / 10k / 100k / 1M), `team.member_limit` (1 / 3 / 10 / 50),
`campaign.inventory_share` (0 / 10 / 30 / 60 %), `support.tier` (Standard / Priority / Dedicated).

## Feature gating

A merchant hitting an unavailable feature sees the feature name, **why it is useful**, their current
plan, the required plan and an upgrade route. The explanation is never hidden — the point is an
informed decision, not a locked door.

## Plan versioning

`PlanVersion` records what changed and when. A subscription keeps the version it was signed on, so
editing a plan never rewrites an existing commercial agreement. Archiving a plan stops new signups
and leaves current subscriptions untouched.

## Custom contracts

Enterprise carries custom pricing, custom limits, contract start/end, renewal type and notice period.
Enterprise is quoted, never self-served: the upgrade button routes to a proposal instead of a checkout.
