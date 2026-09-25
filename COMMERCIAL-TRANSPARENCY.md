# Commercial transparency

## What we tell users, plainly

Comparo may earn money when a user visits a merchant, when a merchant subscribes, and when a merchant
pays for a labelled placement.

**Payment does not buy better reviews. Payment does not buy a higher Trust Score. Payment does not
secretly improve organic ComparoRank.** Commercial status is not an input to any of them.

## Sponsored vs exclusive vs affiliate

* **Sponsored** — the merchant paid for the placement. Always labelled.
* **Comparo Exclusive** — an offer negotiated for Comparo users. May also be sponsored; then it is
  labelled as both.
* **Affiliate** — we may earn commission on an outbound click. Disclosed site-wide and on every
  relevant surface. The user's price never changes.

## Governance checks

The Governance tab asserts seven checks against live prototype data, not against policy prose:
sponsored labelling, rank independence, trust independence, review independence, campaign compliance
(one campaign is genuinely blocked by the FR yohimbine rule), UNKNOWN-compliance review requirement,
and the absence of sensitive targeting.

## Automation limits

Commercial automations may recommend, create a task or notify. **None of them changes a contract, a
price or a merchant entitlement autonomously**, and none sends an external sales message.

## Data boundaries

A merchant cannot see another merchant's contract terms, revenue, billing or internal risk notes.
Users' behavioural history is never exposed publicly, and lead data is shared only after an explicit
user action with recorded consent.

## Route indexing

**Built and public:** `/for-merchants` (free listing, feed, analytics, campaigns, affiliate — with an
explicit statement that ranking is organic).

**NOINDEX and never AI-search indexable:** `/commercial`, `/growth`, `/intel`, billing, invoices,
subscriptions, sales pipeline and API keys. Private commercial data must never become machine-readable.

**Not yet built** (planned public surfaces, deliberately not claimed as existing):
`/for-merchants/pricing` — the plan comparison table currently lives inside the merchant dashboard
and the Commercial console's Plans tab; `/developers` — API overview, docs, plans and status;
`/advertising` — the advertiser disclosure centre. The content for all three exists as builder data
(`cm.planMatrix()`, `cx.apiPlans`, `cx.dataProducts`, and the transparency copy in this document);
only the public routes are missing.
