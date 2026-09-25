# Sales CRM

`CommercialOpportunity { name, type, stage, monthly, annual, probability, closeInDays, owner,
merchantId, notes }` — 17 seeded opportunities.

Types: Subscription, Sponsored campaign, Affiliate partnership, Enterprise contract, API/Data
product, Exclusive partnership.

Stages: Lead → Qualified → Discovery → Proposal → Negotiation → Legal review, with **Won** and
**Lost** as explicit terminal states. Advance clamps at the last funnel stage; winning or losing is a
separate deliberate action, never something the primary button falls into.

## Weighted pipeline

Expected annual value × probability, plus win rate, average deal and value by stage. Winning a
subscription opportunity creates the subscription and keeps the account owner attached.

## Proposal builder

Select merchant, plan, discount, campaign bundle, API package and contract term; the preview shows
products, monthly and annual value, discount and total. Statuses: Draft, Sent, Viewed, Negotiation,
Accepted, Rejected. Nothing is actually sent from the prototype.

## Account 360

One workspace per merchant: health with every component, revenue split four ways, billing profile,
upsell signals, invoices, campaigns, a unified timeline (plan changes, invoices, campaigns) and
internal notes. Header carries status, plan, MRR, health, trust, renewal and owner.

## Internal only

Commercial notes, risk notes, competitor terms and other merchants' billing are never visible to a
merchant. Merchants see their own data and anonymised benchmarks.
