# Orders

## Why this exists

Verification inferred purchases from redirect logs and receipt uploads. Delivery reliability
was a seeded percentage. Returns and disputes had nowhere to live. All four wanted the same
missing thing: **a record of a purchase**.

## The record

```js
{
  id: 'ORD-10042', userId, merchantId, productId, offerId,
  market, qty, unitPrice, itemTotal, shipping, total, currency,
  placedAt, shippedAt, deliveredAt, promisedDays, actualDays,
  status,            // placed | in_transit | delivered | returned | disputed
  carrier, tracking,
  source,            // 'comparo_click' | 'direct'
  clickId,           // present only for clicks we logged ourselves
  reviewId,          // the review this order backs, if any
  returnedAt, returnReason, refund, disputeReason
}
```

## Construction

Built in `seed-orders.js` from two sources, in this order:

1. **Every verified-purchase review gets the order it implies.** A "verified purchase" badge is
   now always backed by a record that can be opened. The review's `verifyMethod` is rewritten
   from the order that was created for it — `affiliate_conversion` when the purchase followed a
   click we logged, `order_reference` otherwise — so the badge and the evidence can never
   disagree.
2. **Purchases nobody reviewed**, roughly six per shop plus a share of its review volume.
   Most purchases are never reviewed, and delivery medians measured only over reviewed orders
   would be biased towards people motivated enough to write.

Dispatch and transit are generated per order against the merchant's own shipping zone, with
verified shops dispatching faster. Nothing about delivery performance is stated; it falls out
of the rows.

## Derived figures

`deliveryStats(merchantId, iso)` returns, over the orders for that market:

| Field | Definition |
|---|---|
| `medianDays` | median of `actualDays` across delivered orders |
| `p90` | the slowest tenth |
| `promised` | mean of `promisedDays` — what checkout said |
| `onTimePct` | share delivered within the promise |
| `returnPct`, `disputePct` | share of all orders in that market |

**Below eight delivered orders in a market it returns nothing at all** and the shop page says
how many it has. A median over three orders is not a median; publishing one would be the same
class of defect this prototype keeps hunting.

## Surfaces

* **Shop page** — "Measured delivery to \<market\>", above the existing feed-derived shipping
  intelligence. The two are kept separate because they measure different things: one is what
  happened to parcels, the other is what the feed claims.
* **Account → Orders** — the shopper's own history, with delivery against promise, carrier and
  tracking, return and dispute state, and a one-click review that verifies itself.
* **Review cards** — a verified label now names its order: "Verified — click matched · ORD-10042".
* **Demo account** — the shopper demo account is bound to seeded user #1 so the tab is populated.

## Still open

* Orders carry one product each. A real basket order has lines; the prototype does not need them
  to make any of the figures above correct, but the backend model should.
* Disputes are a status and a reason, not a workflow. There is no merchant response, no evidence
  upload and no resolution state.
* Returns do not move merchant trust. They are recorded and displayed; `trustScore()` does not
  read them yet.
