# Delivery guarantee

**What it is.** A shop's commitment to a delivery window, published by us only when our own order
records show the shop already meets it, backed by a deposit the shop puts up in advance.

**What it is not.** We are not the seller. We never hold the price of the goods, we cannot refund
a purchase and we do not insure the parcel. What the guarantee covers is *the promise we
published*: if the window is missed the buyer gets the shipping paid back from the shop's
deposit, and the miss is counted publicly against the shop.

## Three tiers

| Tier | Price | Window | Deposit | Claim pays |
|---|---|---|---|---|
| Measured delivery | free for every shop | measured p90 | — | nothing — measurement only |
| Delivery promise | €39 / market / month | p90 rounded up | €400 | shipping refunded |
| Promise 48 h | €89 / market / month | 2 days | €900 | shipping refunded + €5 credit |

## Eligibility is measured, never bought

8 delivered orders in that market · 92 % on-time over 90 days · p90 inside the promised window ·
dispute rate under 2 % · feed fresh within the plan interval · deposit covering 90 days of
expected claims.

**Enrolment is the primary record.** It is derived from the measurement, and the billed add-on
line is generated *from the enrolment*. That ordering is deliberate: if the invoice were the
source, a shop could be billed for a badge it never earned, or carry a badge nobody is billed
for. A shop that wanted the 48-hour tier but whose p90 does not clear two days is published at
the window it earned, with `downgraded: true` on the record.

## The claim

You press "parcel was late" on the order; we already hold the promise, the dispatch and the
delivery date, so there is nothing to prove. Automatic check in ~2 hours, shop may contest with
carrier evidence for 48 hours, paid within 5 working days. When the buyer paid nothing for
shipping there is nothing to refund, so the claim pays a flat €5 credit instead of pretending.

Claim rate is published per shop per market, and the claims table shows rejected claims with the
reason. A guarantee whose failure rate is private is a marketing claim.

## Orders had to be fixed first

The promise a shop makes at checkout covers **dispatch and transit**; `seed-orders.js` was
comparing an order-to-door actual against a transit-only promise, which made every shop look
late (on-time shares of 14–38 %). Promised days are now `zone.days[1] + 1`, dispatch and transit
were tightened, and a thin tail of genuine carrier failures (about one order in thirty: depot
hold, missed attempt, weekend) was added — without it there are no late deliveries at all, which
would make both the guarantee and the p90 meaningless. Measured on-time now runs 92–100 % in
strong shop–market pairs and far lower in weak ones, and the programme's claim rate is about
0.5 % of promised orders.

Surface: `#/delivery-guarantee` (how it works, enrolled shops, all claims, file a claim) and
the delivery-promise panel on `#/for-merchants/addons`.
