# QA

## Sweeps (run before shipping any layout change)

1. **Layout sweep** — the unreachable-content query in RESPONSIVE.md returns 0 at 320/360/390/430/480/640/768/1024/1280/1440/1920 px, and `documentElement.scrollWidth === clientWidth` on every route.
2. **Interaction sweep** — every button, link, input, select and tab is inside the viewport and clickable at each width.
3. **Role sweep** — anonymous cannot reach merchant or staff actions; a merchant cannot reach admin risk scores or another merchant's analytics; staff actions check the acting role in `/intel → Roles`.
4. **Route sweep** — every internal link resolves; no route renders an empty `<main>`.
5. **Accessibility pass** — keyboard-only run of the core consumer flow; contrast check in both themes.
6. **Theme pass** — every major route in light, dark and system.
7. **Performance pass** — heaviest route under the PERFORMANCE.md budget.
8. **State pass** — refresh mid-flow: basket, alerts, saved, follows, compare and tab selections survive.

## Consumer flows

* Search → product → filter offers (chips, counts, empty-state recovery) → compare → save → price alert → affiliate interstitial.
* Search shop → trust breakdown → reviews → discussion → follow → deal.
* Community → topic → reply → vote → notification.
* Basket: add three products → single-shop vs split vs lowest-shipping vs highest-trust → free-shipping nudge.

## Merchant flow

Login → overview → feed health → match centre (explain, confirm, reject) → offers → create deal →
reply to review → competitiveness benchmarks → automations → support.

## Staff flow

Login → merchant approval → compliance → moderation → risk & integrity → review integrity →
price anomaly → affiliate link health → SEO audit → automation pause/run → task queue.

## Regression checklist (never break)

Total-price computation · compliance filtering · ComparoRank explainability · coupon validity
(invalid codes must not discount) · affiliate attribution · role permissions · SEO head sync
(title, canonical, robots, hreflang, JSON-LD) · theme switching · state persistence.
