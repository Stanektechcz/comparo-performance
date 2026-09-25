# Demand signals

A pledge is a shopper saying what they would pay, in a market, for a product we already hold a
price for. It is the only high-intent signal on this site that does not involve buying anything,
and the only one a shop can act on **before** spending a cent on reach.

* We publish the **distribution**, not just the average — the average is where a shop would look
  to justify doing nothing. The page shows the pledge histogram, the clearing price (where half
  the pledges sit at or above) and the gap to the best live price.
* A pledge is not an order and creates no obligation on the shopper. We do not sell, so there is
  nothing to commit to.
* A shop's answer is a **public price commitment with a window**. Honouring it is checked against
  offer history; missing it is recorded on the shop profile like a missed delivery promise.
* We never tell a shop who pledged. Distribution, count and market only.
* Under 300 pledges a signal is labelled **thin** and no shop is asked to answer it.

This is rung 6 of the promotion ladder: free, intrusion level 1, and the highest-intent surface
on the site. It also closes a gap the rest of the product could not: every other signal we hold
is backward-looking.

Files: `seed-network.js` (`net.demand`). Surface: `#/demand`, linked from the promotion ladder.
