# Comparo Performance

Independent price and trust comparison for sports nutrition across 12 European markets.
Comparo ranks offers by **total landed price** — product + shipping + the best applicable coupon —
and publishes the method behind every score.

This repository is a **complete, working browser prototype**. Every score, rule and flow is
implemented and interactive. It is the specification for the Laravel backend, not a mockup of one.

---

## What this is

A comparison and trust platform, not a shop. Comparo never sells products, holds stock or processes
orders. Revenue comes from affiliate commission, merchant subscriptions, labelled sponsored
placements and data products — **none of which influence organic ranking**.

The product answers questions a price table cannot:

* Where is this cheapest **after shipping to my country**?
* Is this actually a good price, against its own 12-month history?
* Has this coupon been working for other people?
* Which shop is most reliable, and how do you know?
* Is this discount real, or computed against an inflated reference price?
* Should I buy everything from one shop, or split the order?
* Why is this offer ranked first?

---

## Running it

Open `Comparo Performance.dc.html` in a browser. No build, no server, no dependencies.
State persists in `localStorage`; reset it from `/design-system` → **Reset demo data**.

### Demo accounts

| Role | Sign-in | Sees |
|---|---|---|
| Shopper | `demo@comparo.example` | saves, alerts, reviews, For you |
| Merchant | `merchant@comparo.example` | feed, matching, offers, deals, analytics, billing |
| Staff | `admin@comparo.example` | Admin, Intelligence, SEO, Growth OS, Commercial OS |

---

## The five sides

**Consumer** — search, compare products and shops, total-price offer tables, price history with a
hover readout, deals and verified coupons, reviews split by product and shop, community, forum,
guides, basket optimiser, price alerts, Ask Comparo.

**Merchant** — feed health and import history, Feed Match Center with confidence and explanation,
offers, deals and campaigns, review replies, competitiveness benchmarks against anonymised category
medians, plan, usage and invoices, API keys.

**Staff** — moderation (including the purchase-proof queue), merchant approval, compliance matrix,
risk and integrity, price anomalies, product merging, affiliate link health, audit log.

**Growth OS** — 33 live opportunities generated from real data, merchant acquisition pipeline,
affiliate CRM, creators, referrals, content engine with a publication quality gate, newsletter,
campaigns, PR and research, market expansion, experiments, growth analytics, briefs.

**Commercial OS** — plans and entitlements, subscriptions, billing and invoices, sponsored
placement marketplace, campaign approval, sales pipeline, renewals, API and data products, revenue
analytics and forecast.

---

## Proprietary scoring

Every score is explainable in the UI — each one has a control that shows its component points.

| Score | Range | Drives |
|---|---|---|
| **ComparoRank** | 0–100 | organic offer ordering. Commission is **not** an input |
| **Trust Score** | 0–100 | merchant reliability, from verification, complaints, feed uptime, price accuracy |
| **Deal Score** | 0–100 | discount against real history, not against a claimed reference |
| **Match confidence** | 0–100 | feed listing → canonical product |
| **Price confidence** | 0–1 | whether a price is trustworthy enough to publish |
| **Review credibility** | weight | how much a review counts in the public average |
| **Opportunity score** | 0–100 | which growth action to take next |

---

## Purchase verification

Shops do not give us order data, so verification rests on evidence we can check ourselves:

1. **Click match** — our own affiliate redirect log. First-party, automatic, and data no competitor
   holds.
2. **Forwarded confirmation** — sent to a personal address; we read sender domain, order date and
   total, check them against what that shop actually charged that week, and discard the message.
3. **Uploaded receipt** — pre-checked, then moderated.

Reviews publish either way. Only the label and the weight change — an unverified review stays
visible and counts less, and is never silently deleted.

---

## Architecture

```
Comparo Performance.dc.html   the application
seed.js                       catalogue, merchants, offers, reviews, price history
seed-seo.js                   page registry, metadata, entity graph
seed-community.js             threads, replies, guides, users
seed-intel.js                 fraud patterns, anomalies, feed conflicts
seed-growth.js                prospects, creators, campaigns, content, research
seed-commercial.js            plans, subscriptions, invoices, campaigns, pipeline
intel.js                      scoring engine — rank, trust, fraud, matching, pricing
growth.js                     opportunity and insight engine
commercial.js                 revenue, billing, forecast
```

`intel.js`, `growth.js` and `commercial.js` are pure deterministic functions over the seed graph.
They port to Laravel services with no logic change — see BACKEND-MIGRATION.md.

---

## Backend handoff

| Document | Contents |
|---|---|
| **BACKEND-MIGRATION.md** | stack, domain model, services, queues, events, caching, build order |
| **API-ENDPOINTS.md** | every endpoint mapped to the prototype function it replaces |
| **AUDIT.md** | what is missing, unconnected or worth improving — including corrections to itself |
| **PROTOTYPE-LIMITATIONS.md** | what this prototype does **not** do, stated plainly |

Machine-readable: `robots.txt`, `llms.txt`, `sitemap.xml` + per-entity sitemaps.

### Three things to get right early

1. **The canonical product graph.** One product, many merchant offers, resolved by EAN/brand/title/
   variant. Without it there is no cross-shop price history and no comparison — it is the moat.
2. **Record every offer's price daily from day one.** Price history cannot be backfilled, and
   fake-discount detection, deal scoring and price confidence all depend on it.
3. **Commission must never reach the ranking service.** Enforce with a test that fails if the
   ranking context grows a commercial property.

---

## Documentation

**Method** — COMPARORANK · TRUST-SCORING · FRAUD-DETECTION · MATCHING-ENGINE · PRICE-INTELLIGENCE ·
RECOMMENDATIONS · PERSONALIZATION · AUTOMATIONS · ATTRIBUTION · MARKET-INTELLIGENCE

**Discovery** — SEO · INDEXING · PROGRAMMATIC-SEO · INTERNATIONAL-SEO · STRUCTURED-DATA ·
KNOWLEDGE-GRAPH · SEARCH · GEO · AI-SEARCH

**Growth** — GROWTH-OS · MERCHANT-ACQUISITION · AFFILIATE-SALES · CREATORS · REFERRALS ·
CONTENT-ENGINE · NEWSLETTER · RESEARCH-PR · GROWTH-ANALYTICS · MARKET-EXPANSION

**Commercial** — COMMERCIAL-OS · MERCHANT-SAAS · PLANS-ENTITLEMENTS · SUBSCRIPTIONS · BILLING ·
INVOICING · SPONSORED-PLACEMENTS · CAMPAIGN-MARKETPLACE · SALES-CRM · RENEWALS · API-MONETIZATION ·
COMMERCIAL-ANALYTICS · REVENUE-FORECAST · COMMERCIAL-TRANSPARENCY

**Platform** — ARCHITECTURE · DATABASE · API · DESIGN-SYSTEM · RESPONSIVE · ACCESSIBILITY ·
PERFORMANCE · STATE-MANAGEMENT · QA · COMPLIANCE · MODERATION · REVIEWS · REPUTATION · COMMUNITY ·
AFFILIATE · MERCHANT-FEEDS · DATA-QUALITY · ANALYTICS

---

## Principles

**Transparency is the product.** Every user-facing score has a published method and an explanation
control. Sponsored placements are labelled and excluded from organic positions. Payment buys tools,
analytics, campaign inventory and data access — never a better ranking, a higher trust score or a
removed review.

**No dark patterns.** No fake urgency, no invented scarcity, no hidden sponsorship, no pre-checked
consent, no misleading countdowns.

**Compliance is not optional.** A product that cannot lawfully be sold in a market is not priced
there. We say so explicitly rather than hiding the product.

**Say what is not known.** Where data is incomplete, the interface says so. Estimates are labelled
estimates. Nothing deterministic is presented as AI.
