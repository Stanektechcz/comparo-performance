# Open business decisions

These are decisions the repository cannot settle: the prototype, seeds and docs either say nothing or
leave them explicitly open. Each one has a **safe, reversible default**, so backend work can start
without anyone making a business decision by accident. A default is a placeholder that keeps options
open. It is not a recommendation to keep.

Rules for defaults:

- A default lives in config or a database table, never in a hard-coded branch, so changing it is a
  data change and not a refactor.
- A default never widens exposure. When in doubt it disables, hides, withholds or does not charge.
- No default touches ranking inputs. Ranking independence is fixed (target decision T).

Cross-references: `C-xx` is a block in [prototype-contradictions.md](prototype-contradictions.md).

## Index

| ID | Decision | Owner | Blocks |
|---|---|---|---|
| D-01 | Launch markets and storefronts | CEO + Head of Growth | Market seed, compliance review scope, merchant acquisition |
| D-02 | Languages at launch and translation scope | Head of Product | Routing, hreflang, lang files, content workflow |
| D-03 | Affiliate networks to integrate first | Head of Partnerships | Revenue path, conversion ingest, reconciliation |
| D-04 | VAT and tax handling | CFO + external tax advisor | Any invoice or charge |
| D-05 | Payment provider and whether to charge at launch | CFO | Subscriptions, add-ons, campaigns |
| D-06 | Currency conversion source | CFO / Finance | Money display, aggregates, reporting |
| D-07 | Per-market offer prices and feed currencies | Head of Data / Merchant Ops | Offer schema, feed contract |
| D-08 | Review and report moderation SLA | Head of Trust & Safety | Staffing, queue alerts, public SLA copy |
| D-09 | Data retention periods | DPO + Legal | Purge jobs, partitions, privacy notice |
| D-10 | Ranking weight ownership and change process | Head of Data / Methodology owner | Ranking Lab publish, methodology page |
| D-11 | Compliance policy for `unknown`, and source of legal rules | Compliance Lead + Legal | Compliance gate, catalogue go-live per market |
| D-12 | Age gating method | Compliance Lead + Legal | Restricted categories, account flows |
| D-13 | Delivery guarantee: deposits and payouts | CFO + Legal | Guarantee add-ons, claims |
| D-14 | Buyer subscriptions (Plus/Pro) and XP-for-Plus | Head of Product + CFO | Consumer billing, entitlements |
| D-15 | Source of order and delivery data | Head of Data + Head of Partnerships | Measured delivery, returns index, labels |
| D-16 | Which seeded Growth/Commercial analytics ship | Head of Product | Growth OS / Commercial OS dashboards |
| D-17 | Affiliate redirect: interstitial and commission disclosure | Head of Partnerships + Legal | `/go` flow, latency target |
| D-18 | Moderation legal framework (DSA) and binding juries | Legal + Head of Trust & Safety | Moderation, juries, takedown log |
| D-19 | Importing third-party review counts / ratings | Head of Trust & Safety | Shop rating display |
| D-20 | One placement inventory and ad pricing model | Head of Commercial | Advertising, campaigns, invoicing |
| D-21 | Scope of the commercial gate (`/developers`) | Head of Commercial | Data-product sales |
| D-22 | Do returns and measured delivery feed the Trust Score | Methodology owner | TrustService inputs |
| D-23 | Device fingerprints and fraud-signal consent | DPO | Fraud scoring, referrals |
| D-24 | Default market for anonymous visitors and crawlers | Head of SEO + Head of Growth | Web SSR pricing, canonical content |
| D-25 | Offer expiry window | Head of Data | Offer visibility, feed pipeline |
| D-26 | Minimum-sample thresholds | Methodology owner | Labels, delivery stats, coupon rates, demand signals |
| D-27 | Transactional and inbound email provider | CTO | Verification by forwarded email, notifications |
| D-28 | Live rooms at launch and real-time transport | Head of Product + CTO | Rooms, presence, price-event messages |
| D-29 | Permissions not present in the prototype | CTO + Head of Operations | Staff RBAC seeder |

---

## D-01 · Launch markets and storefronts

- **Question:** Which delivery markets are active at launch, and which of them get a localised storefront? Is the US (and non-EU GB/CH/NO) in scope for a "European" product?
- **Why it matters:** Every active market needs shipping lanes, compliance baselines for every product, VAT and age rules, merchant coverage and SEO hubs. README says "12 European markets", the data holds 27 delivery markets (C-11), and US is one of the original 12.
- **Safe reversible default:** All 27 markets are loaded as data with `active_delivery = false`. Only **DE and AT** are active and storefront-enabled: the strongest seeded coverage, one language family, EUR, and "Mature" on the expansion board. Everything else is switched on per market by flag. US stays inactive.
- **What depends on it:** Compliance review workload (D-11), merchant acquisition targets, sitemap/hreflang output, FX needs (D-06), VAT tables (D-04), `/countries/{…}` indexability.
- **Owner:** CEO with Head of Growth.

## D-02 · Languages at launch and translation scope

- **Question:** Which UI languages ship at launch, and does translation extend to content (product copy, guides, per-locale URLs) or only to the UI? (BACKEND-READINESS §4.8, AUDIT B9.)
- **Why it matters:** It decides whether `/{locale}` prefixes are live, whether hreflang is real or synthetic, and whether content needs a translation workflow. The seed marks most locales `machine draft` or `missing`.
- **Safe reversible default:** UI-only translation through Laravel lang files, in **English plus German**. Product data stays in its source language with a visible note. The `/{locale}` prefix is reserved in routing. hreflang is emitted only for enabled locales. Machine-drafted strings are never published as reviewed.
- **What depends on it:** Route structure, SEO head generation, content tables (`translation_of_id`), Meilisearch index settings per locale, synonyms.
- **Owner:** Head of Product (with Head of SEO).

## D-03 · Affiliate networks to integrate first

- **Question:** Which of the six networks in `seed-network.js` (Direct, Awin, Tradedoubler, Impact, Daisycon, Partnerize) get a live adapter first?
- **Why it matters:** The redirect is the revenue path. Each network has its own postback format, sub-id limits, validation delay and report API.
- **Safe reversible default:** Build the network-agnostic adapter interface, the signed postback endpoint (C-30) and a **manual CSV report import** usable for any network. Ship **Direct** first, because it needs no partner API. Choose the first API adapter after contracts are signed. Awin is the working assumption only because every doc names it.
- **What depends on it:** Conversion ingest, reconciliation, EPC and commission metrics, click-match verification (click-match needs only our own click log).
- **Owner:** Head of Partnerships.

## D-04 · VAT and tax handling

- **Question:** How are VAT, reverse charge (B2B cross-border), OSS for any consumer charges, VIES validation and invoice content handled per country?
- **Why it matters:** BILLING.md and BACKEND-MIGRATION.md say plainly that the prototype models tax only as *states* and that it is "not a feature you implement from a prototype". A wrong invoice is a legal liability.
- **Safe reversible default:** No live invoicing. Prices are stored and shown **tax-exclusive** for merchants, with the VAT rate held per billing country as data. Invoices are generated as **drafts only** (`ManualInvoiceProvider`), and nothing is sent or charged until a tax advisor signs off. Buyer subscriptions stay disabled (D-14).
- **What depends on it:** D-05, subscriptions, campaign invoicing, add-on billing, credit notes, invoice PDF content.
- **Owner:** CFO with an external tax advisor.

## D-05 · Payment provider and whether to charge at launch

- **Question:** Which provider (Stripe, Adyen, Paddle, manual), which methods (card, SEPA, bank transfer, terms), and does v1 charge at all?
- **Why it matters:** BACKEND-MIGRATION requires a `BillingProvider` abstraction. Enterprise contracts are often invoiced manually.
- **Safe reversible default:** `BillingProvider` interface plus `ManualInvoiceProvider` only. The Stripe adapter is built behind a feature flag and switched off. All plans can be assigned by staff at €0 during a pilot, and the full commercial ledger is still recorded.
- **What depends on it:** Subscription lifecycle, dunning, past-due states, receivables ageing.
- **Owner:** CFO.

## D-06 · Currency conversion source

- **Question:** Which FX source and cadence is used for display conversion and EUR-normalised aggregates?
- **Why it matters:** The prototype uses static rates. The methodology text promises "the daily rate list". Cross-market price comparisons and reporting depend on it.
- **Safe reversible default:** ECB daily euro reference rates, fetched by a scheduled job into `fx_rates(date, quote, rate)` with a stated rate date on every converted figure. The last known rate is used when a fetch fails, with an age warning. Conversion is for display and aggregates only, and stored offer prices keep their source currency (C-02).
- **What depends on it:** Money display, market medians across currencies, Commercial OS reporting in EUR, invoices in foreign currency.
- **Owner:** CFO / Finance.

## D-07 · Per-market offer prices and feed currencies

- **Question:** Can one merchant listing have different prices or currencies per delivery market, or is it one price per listing (as in the prototype)?
- **Why it matters:** The prototype has one EUR price per offer. Only shipping varies by market. Real CZK, PLN or SEK shops and multi-storefront merchants price per market.
- **Safe reversible default:** One current price per `merchant_product` in the feed's currency, plus an optional `offer_market_prices(merchant_product_id, market, price_minor, currency)` override table that stays empty until a feed supplies it. The pricing engine reads the override when present.
- **What depends on it:** Feed contract, price snapshots, the total-price engine, coupon applicability.
- **Owner:** Head of Data with Merchant Operations.

## D-08 · Review and report moderation SLA

- **Question:** Are the MODERATION.md targets (new reviews 24 h, reported content 4 h, reported users 1 h) public commitments, internal targets, or neither, and who staffs them?
- **Why it matters:** Reviews publish only after moderation, so the SLA is the time-to-publish for all user content. Merchant disputes and jury cases also depend on it.
- **Safe reversible default:** Adopt the MODERATION.md numbers as **internal targets**, with queue-depth and age alerts on the admin dashboard. Do not publish them until measured compliance is at or above 90 % for four weeks. Order the queue by ascending review-trust confidence, as in the code.
- **What depends on it:** Moderation alerts, staffing model, public trust-centre copy, merchant reply windows.
- **Owner:** Head of Trust & Safety.

## D-09 · Data retention periods

- **Question:** How long is each class of data kept?
- **Why it matters:** GDPR storage limitation. The docs set only a few periods (feed payloads 30 days, webhook delivery log 30 days) and leave the rest open. The audit log is append-only (C-35).
- **Safe reversible default** (each value is a config entry, and purge jobs run nightly):

| Data | Default |
|---|---|
| Receipt uploads | deleted right after the moderation decision; at most 30 days if undecided |
| Forwarded verification emails | never stored (parsed in memory) |
| Affiliate click log (with session hash) | 24 months in partitions (commission disputes), then aggregated |
| IP / device hashes on reviews and events | 90 days, then nulled |
| Search and event logs | 13 months raw, then aggregates only |
| Price snapshots | indefinitely (the product's core asset; no personal data) |
| Audit log | 6 years, pseudonymous (no email) |
| Feed payload archive | 30 days (MERCHANT-FEEDS) |
| Webhook delivery log | 30 days (API-ENDPOINTS) |
| Deleted accounts | identity erased within 30 days; community content anonymised |

- **What depends on it:** Partition drop jobs, privacy notice, GDPR export and erase, backup policy.
- **Owner:** DPO with Legal.

## D-10 · Ranking weight ownership and change process

- **Question:** Who may change ComparoRank weights (Ranking Lab "Publish as default"), under what review, and how are changes disclosed?
- **Why it matters:** The ranking is the product's credibility. The methodology page must quote live weights (AUDIT I.4). A weight change looks exactly like a market event, and ARCHITECTURE asks for mass-drift alerts.
- **Safe reversible default:** Weights live in versioned `ranking_weight_sets`. Publishing needs `ranking.publish` **and** a second approver, writes an audit entry, and appends a public changelog line on `/methodology`. The seed weights {30/20/14/12/10/8/6 + quality 7} ship as version 1. Two known prototype quirks are ported as-is and listed for this owner to decide: market minimum computed without coupons, and the tie-break on total rather than measured delivery (C-08).
- **What depends on it:** RankingService cache keys, methodology page, drift alerting, Ranking Lab UI.
- **Owner:** Head of Data / Methodology owner.

## D-11 · Compliance policy for `unknown`, and source of legal rules

- **Question:** Should products with `unknown` market status be listed with a warning (prototype code) or hidden (COMPLIANCE.md)? And who researches and signs the per-market rules for supplements, caffeine, melatonin, yohimbine and so on?
- **Why it matters:** Legal exposure per market. The code and COMPLIANCE.md disagree (C-05). All 27 markets need baselines.
- **Safe reversible default:** A config matrix with the code's semantics: `unknown` is listed with "Market status not verified", with no Best value, no recommendations, alerts or sponsorship, and a −6 rank penalty. In any market Legal has not signed off, the matrix switches `unknown` to **blocking**. With D-01 only DE and AT are active, so review scope is small. Every rule records source, reviewer and expiry.
- **What depends on it:** Compliance gate, catalogue go-live per market, programmatic SEO, sponsorship eligibility.
- **Owner:** Compliance Lead with Legal.

## D-12 · Age gating method

- **Question:** How is `min_age` per country enforced for restricted categories: self-declaration, account attribute, or a verification provider?
- **Why it matters:** COMPLIANCE.md says the age gate "cannot be dismissed permanently" but does not say how age is established.
- **Safe reversible default:** A per-session self-declaration gate for categories with a country age rule, recorded in `consent_log`. There is no document verification and no storage of date of birth.
- **What depends on it:** Restricted category pages, redirect to merchants for age-restricted products.
- **Owner:** Compliance Lead with Legal.

## D-13 · Delivery guarantee: deposits and payouts

- **Question:** Can Comparo hold merchant deposits (€400 / €900) and pay buyers shipping refunds and €5 credits from them? Under what legal structure (escrow, payment institution, set-off against invoices)?
- **Why it matters:** Holding third-party money for pass-through payments may be regulated activity. DELIVERY-GUARANTEE.md assumes it without naming a structure.
- **Safe reversible default:** Ship **Measured delivery** (free, measurement only). The Promise and Promise-48 h tiers are built but flag-disabled: no deposits collected, no claim payouts. Claims can be filed and are published as records only.
- **What depends on it:** `promise` add-ons, claims workflow, merchant invoices, guarantee page.
- **Owner:** CFO with Legal.

## D-14 · Buyer subscriptions (Plus/Pro) and XP-for-Plus

- **Question:** Do Comparo Plus (€3.90) and Pro (€8.90) launch, and does 1,200 XP buy 30 days of Plus?
- **Why it matters:** Consumer billing brings OSS VAT, withdrawal rights and consumer contract terms. XP-for-entitlement makes points a quasi-currency.
- **Safe reversible default:** Buyer tiers are modelled as entitlements (`buyer_free|buyer_plus|buyer_pro`) but not sold. Every account resolves to `buyer_free`. XP accrues, and redeeming it is disabled. The free tier already has every trust feature (PAID-ADDONS), so nothing consumer-facing degrades.
- **What depends on it:** Alert capacity limits, history depth, exports, members-only rooms, D-04, D-05.
- **Owner:** Head of Product with CFO.

## D-15 · Source of order and delivery data

- **Question:** Where do order, delivery, return and dispute facts come from in production, given that "Shops do not give us order data"?
- **Why it matters:** Measured delivery, the returns index, delivery labels, promise eligibility and the Trust Score's shipping signal all read order rows (C-12). The prototype generates them.
- **Safe reversible default:** Orders are created only from evidence: an affiliate conversion, a verified proof, or a user declaration linked to a click. Delivery, return and dispute dates come from **shopper-reported events** (the CONTRIBUTIONS delivery-report source, capped and provisional for 48 h). Every derived figure keeps the 8-delivered-order minimum and shows its sample. No merchant order integration is assumed.
- **What depends on it:** `orders`, `order_events`, delivery stats, returns index, labels ("Measured delivery"), D-13.
- **Owner:** Head of Data with Head of Partnerships.

## D-16 · Which seeded Growth/Commercial analytics ship

- **Question:** Roughly a third of Growth OS and Commercial OS figures are scenario constants (AUDIT I.1, PROTOTYPE-LIMITATIONS). Which dashboards ship at launch?
- **Why it matters:** AUDIT's recurring defect class is a number that looks measured but is not. Shipping constants as metrics breaks the transparency principle.
- **Safe reversible default:** Only figures derivable from production tables are shown. Scenario inputs are shown only on a clearly labelled "Scenario" panel with their assumptions, and any widget without a data source is hidden. The forecast stays labelled as a projection (REVENUE-FORECAST).
- **What depends on it:** Growth OS and Commercial OS scope, analytics event pipeline priorities.
- **Owner:** Head of Product.

## D-17 · Affiliate redirect: interstitial and commission disclosure

- **Question:** Does every outbound click go through a disclosure interstitial (prototype, AFFILIATE.md), or a direct 302 (API-ENDPOINTS)? Is a merchant's commission rate shown publicly?
- **Why it matters:** It affects conversion, the latency target, legal disclosure, and network terms that may forbid publishing negotiated rates (AFFILIATE-SALES says terms are not published unless deliberately disclosed).
- **Safe reversible default:** Keep the prototype interstitial: disclosure line, cookie window, an immediate "Continue" link, and auto-continue after 3 s with no urgency styling. The latency target applies to the 302 hop (C-29). Commission is shown as the **category range** from `seed-network.js`, and the merchant-specific rate is flag-disabled until partner contracts allow it.
- **What depends on it:** `/go` routes, click buffer, disclosure copy, network contracts.
- **Owner:** Head of Partnerships with Legal.

## D-18 · Moderation legal framework (DSA) and binding juries

- **Question:** How do notice-and-action, statements of reasons, appeals and trusted-flagger duties map onto the moderation queue and the community juries? Are jury verdicts binding on Comparo?
- **Why it matters:** GOVERNANCE.md keeps one unilateral power (illegal content) and publishes the overturn rate. Platform-law duties may require structures the prototype does not model.
- **Safe reversible default:** Juries are **advisory**, and a named staff role records the final decision. That decision is always published with the jury result and a statement of reasons. Illegal-content removals are logged publicly within 24 h, as GOVERNANCE.md requires. Reporting and appeal flows capture the fields a statement of reasons needs.
- **What depends on it:** Moderation schema (reason codes, appeal state), jury tables, public takedown log.
- **Owner:** Legal with Head of Trust & Safety.

## D-19 · Importing third-party review counts / ratings

- **Question:** Should shop pages show externally sourced review populations (the prototype's "Reviews 3,421" next to "On Comparo 13 weighted")?
- **Why it matters:** DATABASE forbids seeded aggregates, and the prototype blends them (C-14). Imported ratings need a licence and a source.
- **Safe reversible default:** No imports. Ratings are computed only from reviews held on Comparo, and a shop with few reviews shows the count and "limited data".
- **What depends on it:** Shop profile, `AggregateRating` markup, Trust Score review input.
- **Owner:** Head of Trust & Safety.

## D-20 · One placement inventory and ad pricing model

- **Question:** How are the commercial placements (13), the visibility surfaces (12) and the six campaign-marketplace products merged into one sellable catalogue, and which price model applies (C-26)?
- **Why it matters:** Two parallel inventories mean double-selling and two prices for the same slot.
- **Safe reversible default:** Visibility surfaces are the one inventory, and their rules (slots, holdback 20 %, caps, gates, position 1 never sold) are enforced. Prices are computed by the visibility formula and shown only to approved shops. The CPM/CPC model is recorded as the billing basis. Self-serve booking is **disabled** until D-04/D-05, and staff book manually.
- **What depends on it:** Advertising page, campaign approval, invoicing of delivered spend.
- **Owner:** Head of Commercial.

## D-21 · Scope of the commercial gate (`/developers`)

- **Question:** Should API and data-product pricing (`/developers`) sit behind an approved *shop* profile, as in the prototype, when data customers are usually not shops?
- **Why it matters:** The gate logic is right for shops buying visibility, but it may block data sales.
- **Safe reversible default:** Keep the prototype behaviour (gate on every commercial route), with a flag to exempt `/developers` once an alternative "data customer" application exists. The Developer (free) API tier stays self-serve.
- **What depends on it:** API plan sign-up, data-product sales funnel.
- **Owner:** Head of Commercial.

## D-22 · Do returns and measured delivery feed the Trust Score

- **Question:** Should the returns index and measured on-time delivery replace the seeded shipping signals in Trust Score 2.0 (ORDERS.md "Still open")?
- **Why it matters:** Changing trust inputs moves every shop's score and ranking position.
- **Safe reversible default:** Trust uses measured delivery where the sample is at least 8 (same weight as the seeded signal it replaces) and the code fallback otherwise, marked "not measured" (C-15). Returns are **not** a trust input until this owner decides and publishes a methodology change.
- **What depends on it:** TrustService, trust history continuity, methodology page.
- **Owner:** Methodology owner.

## D-23 · Device fingerprints and fraud-signal consent

- **Question:** Which request signals may be used for fraud and referral abuse, under which legal basis, and do they need consent?
- **Why it matters:** FRAUD and REFERRALS use "shared device fingerprint", while AFFILIATE forbids keeping one beyond attribution (C-36).
- **Safe reversible default:** Server-side salted, rotating hashes of a truncated IP and the user-agent class only. No client-side fingerprinting script. Retention follows D-09. Fraud rules that need more are disabled.
- **What depends on it:** Review-trust penalties, referral abuse detection, click dedup.
- **Owner:** DPO.

## D-24 · Default market for anonymous visitors and crawlers

- **Question:** Which market prices a page when there is no user selection, account default or IP hint (the COMPLIANCE cascade end), and is IP geolocation used at all?
- **Why it matters:** INDEXING forbids silently redirecting crawlers. The public API returns 422 without a country (C-22).
- **Safe reversible default:** Default market **DE**, stated on the page ("Prices for delivery to Germany — change"). IP country is used only as a *suggestion* banner, never as an automatic switch.
- **What depends on it:** SSR caching keys, canonical content, structured data offers.
- **Owner:** Head of SEO with Head of Growth.

## D-25 · Offer expiry window

- **Question:** When does a stale offer stop being shown at all? DATA-QUALITY says expired after 7 days, and the code never hides offers by age (C-25).
- **Why it matters:** Publishing week-old prices on a price-comparison site undermines the core promise. Hiding too early empties tables for slow feeds.
- **Safe reversible default:** Keep the code behaviour: stock shows "Unknown" after 48 h, and ranking applies the −10 stale penalty. An offer is additionally **deactivated** when it is missing from two consecutive successful feed runs of its merchant, or not seen for 7 days, whichever comes first. Both are config values.
- **What depends on it:** Feed diff/commit step, offer counts, sitemap and indexability rules.
- **Owner:** Head of Data.

## D-26 · Minimum-sample thresholds

- **Question:** Who owns the publication thresholds: 8 delivered orders (delivery, returns, labels), 12 coupon reports, 300 demand pledges, 5/4 reviews for labels, 3 returns for fault scoring?
- **Why it matters:** They decide what is published at all. AUDIT K records thresholds re-pitched because labels were unearnable on seed data. Production data volumes will differ.
- **Safe reversible default:** Every threshold is a named config value set to the prototype number. Any change goes through the methodology changelog (D-10 process), and the published criteria copy is interpolated from config.
- **What depends on it:** Labels, delivery stats, coupon success rates, demand signals, returns index.
- **Owner:** Methodology owner.

## D-27 · Transactional and inbound email provider

- **Question:** Which provider handles transactional mail and **inbound parsing** for the forwarded-confirmation verification route (personal `verify+…@` addresses)?
- **Why it matters:** The email route needs inbound webhooks and a dedicated domain. BACKEND-MIGRATION suggests Postmark or Mailgun without choosing.
- **Safe reversible default:** Laravel mail through a provider-agnostic driver for outbound. The forwarded-email verification route is **disabled**. Click-match and receipt upload are the launch routes, so no inbound mail infrastructure is needed on day one.
- **What depends on it:** Verification route 2, digest and alert emails, bounce handling.
- **Owner:** CTO.

## D-28 · Live rooms at launch and real-time transport

- **Question:** Do the 38 live rooms (price events as messages, presence, slow mode) launch, and on what transport?
- **Why it matters:** Real-time infrastructure, moderation load and abuse surface. LIVE-ROOMS.md says a socket replaces the BroadcastChannel transport.
- **Safe reversible default:** Rooms are disabled by feature flag. Promotion-into-topic and price-event records are stored, so enabling rooms later has history to show. When enabled, use Laravel's first-party WebSocket server behind the same moderation pipeline.
- **What depends on it:** Community scope, moderation staffing (D-08), desk-hour advertising format.
- **Owner:** Head of Product with CTO.

## D-29 · Permissions not present in the prototype

- **Question:** Confirm the three new permissions (`pricing.review`, `growth.view`, `commercial.view`) and the merge of the duplicate roles "Affiliate Manager" and "Analyst" (C-20).
- **Why it matters:** Least privilege for staff consoles. Without them, Growth/Commercial read access would ride on broader permissions.
- **Safe reversible default:** Create the three permissions and grant them only to Super Admin and the matching commercial or growth roles. Merged roles get the **intersection** of the two prototype permission sets until the owner approves the union.
- **What depends on it:** Staff RBAC seeder, policy tests, audit coverage.
- **Owner:** CTO with Head of Operations.
