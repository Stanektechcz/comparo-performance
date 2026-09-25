# Performance

## Budget

| Target | Value |
|---|---|
| First render | paints from the first streamed markup, no blocking CSS classes for layout |
| Local navigation | instant (hash routing, no refetch) |
| Perceived search response | < 100 ms |
| Heaviest route render | < 350 ms of scripting |

## What made it fast

* **Per-render row cache.** `offerRow()` memoises by `(offerId, country, currency)` and the cache is
  cleared once per `renderVals()`. The home route computes every offer in the catalogue; before the
  cache that was ~930 ms of scripting, after it ~305 ms.
* **Market-stats cache.** `marketStats(productId)` caches per product and delivery country inside a
  render pass, so a 14-row offer table computes market minimum and median once.
* **Engine memoisation.** `intel.js` keys every expensive score (trust, risk, review trust, match,
  history statistics, completion, price confidence, anomalies, coverage, trends, tasks, data health)
  by entity id and returns the cached object. Scores derive from immutable seed data, so the cache
  survives across renders; `trust()` includes the merchant rating in its key so admin overrides
  invalidate it.
* **Debounced persistence.** `persist()` schedules `persistNow()` 220 ms later, so typing in a
  review or filter box no longer serialises the whole store on every keystroke.
* **Debounced layout sweep.** The scroll-affordance pass runs 40 ms after an update and 120 ms after
  a resize, never per row.

## Still to do for production

Virtualise lists beyond a few hundred rows; lazy-load the entity graph and the heavier admin
visualisations; move the scoring engine behind the services in ARCHITECTURE.md so the browser
receives precomputed scores instead of computing them.
