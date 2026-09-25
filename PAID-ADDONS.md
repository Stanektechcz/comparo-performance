# Paid extensions

The revenue model has to survive one sentence: **we do not sell the products.** No marketplace,
no basket, no order of ours — a click leaves for the shop's own checkout. So everything paid is
one of exactly three kinds, and anything that is not one of them is not for sale.

## 1. Merchant capability (14 add-ons, €19–€149)

Delivery promise per market · Promise 48 h · hourly feed import · stock webhook · analytics
history to 365 days · competitor price alerts · review invitations · Q&A concierge · label
verification · market packs · extra deal slots · campaign inventory share · team seats · support
SLA.

Each add-on declares its **entitlement delta**, its **gate** (what must be true before it can be
bought) and its **cancellation terms**. Deltas resolve on top of the plan floor, and an interval
is a floor rather than a sum — hourly import replaces six-hourly instead of adding to it.

**The stack check.** If active add-ons cost more than the difference to the next plan, the console
says so and offers the upgrade, cancelling the add-ons the higher plan already covers. We would
rather move a shop up a plan than let an add-on stack quietly overtake it.

Not purchasable at any price: ComparoRank position, review weight or visibility, any trust badge,
a delivery promise not measured up to, a place in an editorial comparison.

## 2. Buyer capability — Comparo Plus €3.90 / Pro €8.90

Free is not a trial. The offer table, its order, every total, trust scores, review weighting,
dosing, price per gram of active, the forum, the rooms and the right to file a delivery claim are
identical on every tier. Paid tiers add history depth, alert capacity and instant alerts,
cross-market comparison, exports, a personal API token, members-only rooms, group creation and
claim assistance.

**1,200 XP is 30 days of Plus.** Roughly a fifth of Plus accounts have never paid money for it.

## 3. Declared advertising

13 placements, 8 formats, audience **computed from measured entrances** per page type rather than
typed onto the record (this closes AUDIT § I.2). Targeting by market, category, ingredient, price
band, device and session intent; never by identity, health status, a named person's purchase
history, or anything from a data broker — we buy no audience data at all. A self-serve estimator
turns placement + markets + flight + budget into impressions, clicks, effective CPM and CPC, and
refuses to oversell a capped slot.

Six revenue lines are rendered on the advertising page from the records they are billed against.

## Files

`seed-addons.js` (add-on catalogue, seeded stacks, buyer tiers, guarantee programme, ad
inventory, revenue mix) and `addons.js` (`ComparoAddons(SEED)`: merchant entitlement resolver,
upgrade advice, buyer entitlement resolver, tier matrix, promise eligibility, claims, ad
estimates). Surfaces: `#/for-merchants/addons`, `#/plus`, `#/advertising`,
`#/delivery-guarantee`.
