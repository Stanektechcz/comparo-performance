# API & data monetisation

## Plans

Developer (free, 5k requests, 5 req/s, community support) · Business (€490/mo, 250k requests,
25 req/s, priority, €4 per 1k overage) · Enterprise (quoted, custom limits, dedicated support).

## Data products

Product Catalog, Price, Price History, Merchant, Deal, Review Aggregate and Market Intelligence APIs.

**Compliance boundaries:** no personal user data is ever exposed, and no internal fraud, risk or
review-integrity signal is exposed publicly. Review APIs serve aggregates only.

## Keys

Create, name, revoke. Every prototype key is prefixed `demo_pk_…` and labelled as a prototype key —
the UI never presents a production-looking credential, and no real secret is generated.

## Usage and overage

Requests used against included volume, error count, success rate, top endpoint, and an overage
estimate priced per 1,000 requests. At 85 % of plan the upsell automation creates an opportunity —
one seeded customer sits at 94 %.

## Webhooks

Endpoint, events, status and last delivery, with a delivery log including HTTP code and latency.
Events: `price.updated`, `offer.created`, `deal.created`, `merchant.rating_changed`.
One seeded endpoint is failing with 503 so the degraded state is visible.

## Reports

Four premium market-intelligence reports. Free tier gets a genuine preview metric; the full report is
a one-off purchase recorded as an invoice item. No payment is collected in the prototype.
