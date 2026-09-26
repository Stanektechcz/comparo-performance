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

## Decision status (2026-09-26)

On 2026-09-26 the repository owner delegated every open point to the engineering organisation
("rozhodni všechny body", i.e. "decide all the points"). Each D-xx below now ends with a
**Decision (2026-09-26)** block. The original question, rationale and default above each block are
kept unchanged as history. The consolidated table is
[ADR-0018](../adr/0018-business-decisions-baseline.md).

- **DECIDED** means the engineering default is final for the first launch and is represented, or
  will be represented, in config, a feature flag or data.
- **DECIDED — legal verification before production** means the engineering default is decided,
  but the topic legally needs professional sign-off (tax advisor, Legal or DPO) before a production
  launch. **No such sign-off exists yet.**
- **DECIDED — legal verification before enabling** means the feature is off at launch, and sign-off
  is needed before the flag is turned on.
- Decisions choose integration architecture and defaults. They do not claim contracts, partners,
  accounts or credentials that do not exist. Those remain human actions (ADR-0018, section "Still
  requires a human").

## Index

| ID | Decision | Owner | Blocks | Status (2026-09-26) |
|---|---|---|---|---|
| D-01 | Launch markets and storefronts | CEO + Head of Growth | Market seed, compliance review scope, merchant acquisition | DECIDED |
| D-02 | Languages at launch and translation scope | Head of Product | Routing, hreflang, lang files, content workflow | DECIDED |
| D-03 | Affiliate networks to integrate first | Head of Partnerships | Revenue path, conversion ingest, reconciliation | DECIDED |
| D-04 | VAT and tax handling | CFO + external tax advisor | Any invoice or charge | DECIDED — legal verification before production |
| D-05 | Payment provider and whether to charge at launch | CFO | Subscriptions, add-ons, campaigns | DECIDED |
| D-06 | Currency conversion source | CFO / Finance | Money display, aggregates, reporting | DECIDED |
| D-07 | Per-market offer prices and feed currencies | Head of Data / Merchant Ops | Offer schema, feed contract | DECIDED |
| D-08 | Review and report moderation SLA | Head of Trust & Safety | Staffing, queue alerts, public SLA copy | DECIDED |
| D-09 | Data retention periods | DPO + Legal | Purge jobs, partitions, privacy notice | DECIDED — legal verification before production |
| D-10 | Ranking weight ownership and change process | Head of Data / Methodology owner | Ranking Lab publish, methodology page | DECIDED |
| D-11 | Compliance policy for `unknown`, and source of legal rules | Compliance Lead + Legal | Compliance gate, catalogue go-live per market | DECIDED — legal verification before production |
| D-12 | Age gating method | Compliance Lead + Legal | Restricted categories, account flows | DECIDED — legal verification before production |
| D-13 | Delivery guarantee: deposits and payouts | CFO + Legal | Guarantee add-ons, claims | DECIDED — legal verification before enabling |
| D-14 | Buyer subscriptions (Plus/Pro) and XP-for-Plus | Head of Product + CFO | Consumer billing, entitlements | DECIDED — legal verification before enabling |
| D-15 | Source of order and delivery data | Head of Data + Head of Partnerships | Measured delivery, returns index, labels | DECIDED |
| D-16 | Which seeded Growth/Commercial analytics ship | Head of Product | Growth OS / Commercial OS dashboards | DECIDED |
| D-17 | Affiliate redirect: interstitial and commission disclosure | Head of Partnerships + Legal | `/go` flow, latency target | DECIDED — legal verification before production |
| D-18 | Moderation legal framework (DSA) and binding juries | Legal + Head of Trust & Safety | Moderation, juries, takedown log | DECIDED — legal verification before production |
| D-19 | Importing third-party review counts / ratings | Head of Trust & Safety | Shop rating display | DECIDED |
| D-20 | One placement inventory and ad pricing model | Head of Commercial | Advertising, campaigns, invoicing | DECIDED — legal verification before enabling |
| D-21 | Scope of the commercial gate (`/developers`) | Head of Commercial | Data-product sales | DECIDED |
| D-22 | Do returns and measured delivery feed the Trust Score | Methodology owner | TrustService inputs | DECIDED |
| D-23 | Device fingerprints and fraud-signal consent | DPO | Fraud scoring, referrals | DECIDED — legal verification before production |
| D-24 | Default market for anonymous visitors and crawlers | Head of SEO + Head of Growth | Web SSR pricing, canonical content | DECIDED |
| D-25 | Offer expiry window | Head of Data | Offer visibility, feed pipeline | DECIDED |
| D-26 | Minimum-sample thresholds | Methodology owner | Labels, delivery stats, coupon rates, demand signals | DECIDED |
| D-27 | Transactional and inbound email provider | CTO | Verification by forwarded email, notifications | DECIDED |
| D-28 | Live rooms at launch and real-time transport | Head of Product + CTO | Rooms, presence, price-event messages | DECIDED |
| D-29 | Permissions not present in the prototype | CTO + Head of Operations | Staff RBAC seeder | DECIDED |

---

## D-01 · Launch markets and storefronts

- **Question:** Which delivery markets are active at launch, and which of them get a localised storefront? Is the US (and non-EU GB/CH/NO) in scope for a "European" product?
- **Why it matters:** Every active market needs shipping lanes, compliance baselines for every product, VAT and age rules, merchant coverage and SEO hubs. README says "12 European markets", the data holds 27 delivery markets (C-11), and US is one of the original 12.
- **Safe reversible default:** All 27 markets are loaded as data with `active_delivery = false`. Only **DE and AT** are active and storefront-enabled: the strongest seeded coverage, one language family, EUR, and "Mature" on the expansion board. Everything else is switched on per market by flag. US stays inactive.
- **What depends on it:** Compliance review workload (D-11), merchant acquisition targets, sitemap/hreflang output, FX needs (D-06), VAT tables (D-04), `/countries/{…}` indexability.
- **Owner:** CEO with Head of Growth.

### Decision (2026-09-26) · DECIDED

- **Decided:** The default is adopted. **DE and AT** are the only launch markets, both delivery-active and storefront-enabled. The other 25 markets are loaded as data and inactive. US, GB, CH and NO are out of scope for v1, because each non-EU market needs its own customs, VAT and legal review. A market is activated only by an audited staff action, and only after three things exist: a signed compliance baseline (D-11), VAT data (D-04) and merchant coverage.
- **Rationale:** DE/AT have the strongest seeded coverage, use one currency (EUR) and one language family, and keep the compliance review scope small. This is the smallest exposure that is still a real product.
- **Represented in:** `countries.is_active`. Planned config `comparo.markets.launch` (env `COMPARO_LAUNCH_MARKETS`, default `DE,AT`), read by a production market seeder. The demo import (`PrototypeSnapshotImporter`) keeps all 27 markets active, so parity is unaffected. Search indexes only active markets (A-22).
- **Reversibility:** This is a data flag per country. Activating a market triggers a full reindex (A-22).
- **Production prerequisite:** The D-11 legal sign-off for DE and AT. Which markets come after DE/AT is a commercial choice that can change at any time.

## D-02 · Languages at launch and translation scope

- **Question:** Which UI languages ship at launch, and does translation extend to content (product copy, guides, per-locale URLs) or only to the UI? (BACKEND-READINESS §4.8, AUDIT B9.)
- **Why it matters:** It decides whether `/{locale}` prefixes are live, whether hreflang is real or synthetic, and whether content needs a translation workflow. The seed marks most locales `machine draft` or `missing`.
- **Safe reversible default:** UI-only translation through Laravel lang files, in **English plus German**. Product data stays in its source language with a visible note. The `/{locale}` prefix is reserved in routing. hreflang is emitted only for enabled locales. Machine-drafted strings are never published as reviewed.
- **What depends on it:** Route structure, SEO head generation, content tables (`translation_of_id`), Meilisearch index settings per locale, synonyms.
- **Owner:** Head of Product (with Head of SEO).

### Decision (2026-09-26) · DECIDED

- **Decided:** The default is adopted. The UI ships in **English and German** through Laravel lang files. Product data stays in its source language and carries a visible note. `/{locale}` is reserved in routing and goes live with Phase 11. hreflang is emitted only for enabled locales. Machine-drafted strings are never published as reviewed. The UI locale is negotiated from `Accept-Language` among the enabled locales, falls back to `en`, and is independent of the market (ADR-0008).
- **Rationale:** German is needed for the DE/AT launch (D-01). Translating content would need an editorial workflow that does not exist yet.
- **Represented in:** `app.locale` / `app.fallback_locale` (`en`) and planned config `comparo.locales.enabled` = `['en', 'de']`.
- **Reversibility:** A config list. The locale prefix is additive, and old routes get 301 redirects (A-20).
- **Production prerequisite (human):** German versions of the legal pages (imprint, privacy notice, terms, affiliate disclosure), reviewed by counsel.

## D-03 · Affiliate networks to integrate first

- **Question:** Which of the six networks in `seed-network.js` (Direct, Awin, Tradedoubler, Impact, Daisycon, Partnerize) get a live adapter first?
- **Why it matters:** The redirect is the revenue path. Each network has its own postback format, sub-id limits, validation delay and report API.
- **Safe reversible default:** Build the network-agnostic adapter interface, the signed postback endpoint (C-30) and a **manual CSV report import** usable for any network. Ship **Direct** first, because it needs no partner API. Choose the first API adapter after contracts are signed. Awin is the working assumption only because every doc names it.
- **What depends on it:** Conversion ingest, reconciliation, EPC and commission metrics, click-match verification (click-match needs only our own click log).
- **Owner:** Head of Partnerships.

### Decision (2026-09-26) · DECIDED

- **Decided:** Build the network-agnostic adapter interface, the signed postback endpoint (C-30) and a manual CSV report import for any network. **Direct** ships first. **Awin** is the first API adapter, built and tested against fixtures or a sandbox only. No network API adapter is enabled in production until a contract and credentials exist.
- **Rationale:** Direct needs no partner API. Every doc names Awin, so it is the lowest-risk first build. Keeping the adapter disabled avoids implying a partnership that does not exist.
- **Represented in:** Phase 5. Planned config `comparo.affiliate.networks` (enabled list, default `['direct']`), with network credentials kept only in env or a secret store. Planned flag `affiliate-network-api` (off).
- **Reversibility:** The enabled list is config, and adapters are behind one interface.
- **Production prerequisite (human):** Signed network contracts and publisher accounts, plus API credentials and postback secrets.

## D-04 · VAT and tax handling

- **Question:** How are VAT, reverse charge (B2B cross-border), OSS for any consumer charges, VIES validation and invoice content handled per country?
- **Why it matters:** BILLING.md and BACKEND-MIGRATION.md say plainly that the prototype models tax only as *states* and that it is "not a feature you implement from a prototype". A wrong invoice is a legal liability.
- **Safe reversible default:** No live invoicing. Prices are stored and shown **tax-exclusive** for merchants, with the VAT rate held per billing country as data. Invoices are generated as **drafts only** (`ManualInvoiceProvider`), and nothing is sent or charged until a tax advisor signs off. Buyer subscriptions stay disabled (D-14).
- **What depends on it:** D-05, subscriptions, campaign invoicing, add-on billing, credit notes, invoice PDF content.
- **Owner:** CFO with an external tax advisor.

### Decision (2026-09-26) · DECIDED — legal verification before production

- **Decided:** The default is adopted. There is no live invoicing. Merchant prices are stored and shown **tax-exclusive**, and the VAT rate per billing country is held as data (`countries.standard_vat_rate` is informational only). Invoices are **drafts only**, through `ManualInvoiceProvider`. Reverse charge, OSS and VIES logic is not implemented until a tax advisor has specified it. No consumer charges are made (D-14).
- **Rationale:** A wrong invoice is a legal liability. The prototype models tax only as states.
- **Represented in:** ADR-0006. Planned flag `billing-live-invoicing` (off).
- **Reversibility:** A flag. Drafts can be regenerated.
- **Production prerequisite (human):** An external tax advisor signs off invoice content, reverse charge, VIES validation, OSS and the retention of accounting records, before any invoice is sent. **This requires legal/tax verification before production launch.**

## D-05 · Payment provider and whether to charge at launch

- **Question:** Which provider (Stripe, Adyen, Paddle, manual), which methods (card, SEPA, bank transfer, terms), and does v1 charge at all?
- **Why it matters:** BACKEND-MIGRATION requires a `BillingProvider` abstraction. Enterprise contracts are often invoiced manually.
- **Safe reversible default:** `BillingProvider` interface plus `ManualInvoiceProvider` only. The Stripe adapter is built behind a feature flag and switched off. All plans can be assigned by staff at €0 during a pilot, and the full commercial ledger is still recorded.
- **What depends on it:** Subscription lifecycle, dunning, past-due states, receivables ageing.
- **Owner:** CFO.

### Decision (2026-09-26) · DECIDED

- **Decided:** The `BillingProvider` interface is kept, and **`ManualInvoiceProvider` is the only active provider**. **Stripe** is the first card/SEPA adapter, built and tested in Stripe test mode only, behind the flag `billing-stripe` (off). **v1 does not charge.** Staff can assign plans at €0 during a pilot, and the commercial ledger is still recorded.
- **Rationale:** ADR-0006 already names Stripe as the first external adapter. Enterprise contracts are invoiced manually anyway.
- **Represented in:** ADR-0006. Planned config `comparo.billing.provider` = `manual` and planned flag `billing-stripe` (off).
- **Reversibility:** Provider selection is config, behind one interface.
- **Production prerequisite (human):** A Stripe (or other) merchant account with KYC, live keys in a secret store, a CFO decision to charge, and the D-04 sign-off.

## D-06 · Currency conversion source

- **Question:** Which FX source and cadence is used for display conversion and EUR-normalised aggregates?
- **Why it matters:** The prototype uses static rates. The methodology text promises "the daily rate list". Cross-market price comparisons and reporting depend on it.
- **Safe reversible default:** ECB daily euro reference rates, fetched by a scheduled job into `fx_rates(date, quote, rate)` with a stated rate date on every converted figure. The last known rate is used when a fetch fails, with an age warning. Conversion is for display and aggregates only, and stored offer prices keep their source currency (C-02).
- **What depends on it:** Money display, market medians across currencies, Commercial OS reporting in EUR, invoices in foreign currency.
- **Owner:** CFO / Finance.

### Decision (2026-09-26) · DECIDED

- **Decided:** Rates come from the **ECB daily euro reference rates**. A scheduled job fetches them on TARGET working days after ECB publication and appends them to the existing `exchange_rates` table (`source = 'ecb'`). That table name replaces `fx_rates` in the default above. Every converted figure states its rate date, which `CurrencyConversion` already carries. The last known rate is used, with an age warning after **4 days**. Once the import job ships, rates older than **14 days** count as unknown, so the offer drops out of the cross-currency comparison as in A-29. Conversion is for display and aggregates only, and stored prices keep their source currency.
- **Rationale:** The ECB rates are free, official and match the "daily rate list" in the methodology. A hard age limit stops a silently frozen feed from producing comparisons that look precise but are wrong. The launch markets are EUR-only, so FX affects only non-EUR feeds and display.
- **Represented in:** `App\Domain\Pricing\Currency\ExchangeRates` and `comparo.comparison_currency` (EUR). Planned config `comparo.fx.source` = `ecb`, `comparo.fx.stale_warning_days` = 4 and `comparo.fx.max_rate_age_days`. The last one stays `null` (no cut-off) until the import job exists, because the demo and test rates are historical.
- **Reversibility:** Config values. The source adapter can be swapped.
- **Production prerequisite:** None.

## D-07 · Per-market offer prices and feed currencies

- **Question:** Can one merchant listing have different prices or currencies per delivery market, or is it one price per listing (as in the prototype)?
- **Why it matters:** The prototype has one EUR price per offer. Only shipping varies by market. Real CZK, PLN or SEK shops and multi-storefront merchants price per market.
- **Safe reversible default:** One current price per `merchant_product` in the feed's currency, plus an optional `offer_market_prices(merchant_product_id, market, price_minor, currency)` override table that stays empty until a feed supplies it. The pricing engine reads the override when present.
- **What depends on it:** Feed contract, price snapshots, the total-price engine, coupon applicability.
- **Owner:** Head of Data with Merchant Operations.

### Decision (2026-09-26) · DECIDED

- **Decided:** The default is adopted. There is one current price per `merchant_product`, in the feed's currency. An optional `offer_market_prices(merchant_product_id, market, price_minor, currency)` override table stays empty until a feed supplies it, and the pricing engine reads the override when present.
- **Rationale:** This matches the prototype and the launch markets (EUR only), and it is additive for real per-market pricing.
- **Represented in:** A planned schema, built when the first per-market feed is onboarded. Feed-level shipping is stored raw (A-13).
- **Reversibility:** An additive table.
- **Production prerequisite:** None.

## D-08 · Review and report moderation SLA

- **Question:** Are the MODERATION.md targets (new reviews 24 h, reported content 4 h, reported users 1 h) public commitments, internal targets, or neither, and who staffs them?
- **Why it matters:** Reviews publish only after moderation, so the SLA is the time-to-publish for all user content. Merchant disputes and jury cases also depend on it.
- **Safe reversible default:** Adopt the MODERATION.md numbers as **internal targets**, with queue-depth and age alerts on the admin dashboard. Do not publish them until measured compliance is at or above 90 % for four weeks. Order the queue by ascending review-trust confidence, as in the code.
- **What depends on it:** Moderation alerts, staffing model, public trust-centre copy, merchant reply windows.
- **Owner:** Head of Trust & Safety.

### Decision (2026-09-26) · DECIDED

- **Decided:** The default is adopted. The MODERATION.md figures (new reviews 24 h, reported content 4 h, reported users 1 h) are **internal targets** with queue-depth and age alerts. They are **not published** until measured compliance has been at least 90 % for four consecutive weeks. The queue is ordered by ascending review-trust confidence. Illegal-content handling follows D-18.
- **Rationale:** Publishing an SLA that has never been measured would be a promise the organisation might not keep.
- **Represented in:** Planned config `comparo.moderation.targets_hours` = `{new_review: 24, reported_content: 4, reported_user: 1}` and `comparo.moderation.publish_sla` = `false`.
- **Reversibility:** Config values.
- **Production prerequisite (human):** A staffing plan (named moderators and a rota) before user reviews go live.

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

### Decision (2026-09-26) · DECIDED — legal verification before production

- **Decided:** The table above is adopted as the engineering retention schedule. Every value is a config entry, and purge jobs run nightly. The feed and search values already enforced are confirmed: payloads 30 days, staged items 7 days, session hashes 90 days, raw search rows 13 months. The audit log stays append-only and pseudonymous (C-35). Invoices and accounting records follow the statutory period the tax advisor confirms (D-04), not this table.
- **Rationale:** GDPR storage limitation. Each period is the shortest that still serves its stated purpose (disputes, fraud, audit).
- **Represented in:** Existing keys `comparo.feeds.payload_retention_days`, `comparo.feeds.item_retention_days`, `comparo.search.analytics.*` and `docs/privacy/data-retention.md`. Planned `comparo.retention.*` keys: `receipt_max_days` 30, `affiliate_click_months` 24, `request_hash_days` 90, `event_log_months` 13, `audit_log_years` 6, `account_erasure_days` 30, `webhook_log_days` 30.
- **Reversibility:** Config values. Shortening a period is always safe.
- **Production prerequisite (human):** The DPO and Legal sign off the schedule, the privacy notice text, the alignment with backup retention and the record of processing activities. **This requires legal/DPO verification before production launch.**

## D-10 · Ranking weight ownership and change process

- **Question:** Who may change ComparoRank weights (Ranking Lab "Publish as default"), under what review, and how are changes disclosed?
- **Why it matters:** The ranking is the product's credibility. The methodology page must quote live weights (AUDIT I.4). A weight change looks exactly like a market event, and ARCHITECTURE asks for mass-drift alerts.
- **Safe reversible default:** Weights live in versioned `ranking_weight_sets`. Publishing needs `ranking.publish` **and** a second approver, writes an audit entry, and appends a public changelog line on `/methodology`. The seed weights {30/20/14/12/10/8/6 + quality 7} ship as version 1. Two known prototype quirks are ported as-is and listed for this owner to decide: market minimum computed without coupons, and the tie-break on total rather than measured delivery (C-08).
- **What depends on it:** RankingService cache keys, methodology page, drift alerting, Ranking Lab UI.
- **Owner:** Head of Data / Methodology owner.

### Decision (2026-09-26) · DECIDED

- **Decided:** Weights are versioned. The existing `ranking_versions` / `ranking_weights` tables replace the `ranking_weight_sets` name in the default above. Publishing a weight set needs a dedicated **`ranking.publish`** permission (Super Admin by default) and **a second, different approver** who also holds it (four-eyes). Each publish writes an audit entry and appends a public changelog line on `/methodology`. The seed weights {30/20/14/12/10/8/6 + quality 7} ship as version 1 (`prototype-v1`). `ranking.configure` covers drafting and simulating only. The two prototype quirks are kept for v1: (a) the market minimum is computed without coupons, which is conservative because coupons are conditional; (b) the tie-break is the lowest total. A measured-delivery tie-break stays a proposal until measured delivery has sample coverage (D-22, D-26). Any change goes through the changelog.
- **Rationale:** The ranking is the product's credibility. Four-eyes review plus a public changelog makes weight changes accountable and visible. Keeping the quirks preserves parity (ADR-0010).
- **Represented in:** `RankingWeights::PROTOTYPE_DEFAULTS`, `RankingVersion` and `Permission::ConfigureRanking` (existing). A planned new permission `ranking.publish`, added to Super Admin only. This is a code change for the Ranking Lab (Phase 8).
- **Reversibility:** Versioned data, so a rollback is activating a previous version.
- **Production prerequisite (human):** Name the methodology owner and the second approver.

## D-11 · Compliance policy for `unknown`, and source of legal rules

- **Question:** Should products with `unknown` market status be listed with a warning (prototype code) or hidden (COMPLIANCE.md)? And who researches and signs the per-market rules for supplements, caffeine, melatonin, yohimbine and so on?
- **Why it matters:** Legal exposure per market. The code and COMPLIANCE.md disagree (C-05). All 27 markets need baselines.
- **Safe reversible default:** A config matrix with the code's semantics: `unknown` is listed with "Market status not verified", with no Best value, no recommendations, alerts or sponsorship, and a −6 rank penalty. In any market Legal has not signed off, the matrix switches `unknown` to **blocking**. With D-01 only DE and AT are active, so review scope is small. Every rule records source, reviewer and expiry.
- **What depends on it:** Compliance gate, catalogue go-live per market, programmatic SEO, sponsorship eligibility.
- **Owner:** Compliance Lead with Legal.

### Decision (2026-09-26) · DECIDED — legal verification before production

- **Decided:** The `unknown` semantics are those fixed by ADR-0007 and C-05. Prices are shown for information only, with no purchase CTA or outbound link, no Best value, no recommendations, alerts or sponsorship, and a −6 rank penalty. The product enters the review queue. This is **not** a config switch, and changing it needs a new ADR. **Amended default:** there is no per-market "unknown → blocking" matrix. Instead, a market can be activated (D-01) only after Legal has signed off its baseline rule set, so a market without sign-off exposes nothing at all. Every rule records source, reviewer and review date (existing columns) plus a planned `expires_at` re-review date (default 12 months). An expired rule resolves to `unknown`. The Compliance Lead drafts rules from official sources, and Legal signs them.
- **Rationale:** Gating whole markets is stricter and simpler than a status matrix, and it reuses the existing activation flag.
- **Represented in:** `App\Domain\Compliance\ComplianceStatus`, `ComplianceDecision::unreviewed()`, `product_compliance_rules`, `countries.is_active`. Planned `product_compliance_rules.expires_at` and planned config `comparo.compliance.rule_review_months` = 12.
- **Reversibility:** Rules are data, and markets are a flag.
- **Production prerequisite (human):** Legal signs off the DE and AT baselines for the launch catalogue's categories. **This requires legal verification before production launch.**

## D-12 · Age gating method

- **Question:** How is `min_age` per country enforced for restricted categories: self-declaration, account attribute, or a verification provider?
- **Why it matters:** COMPLIANCE.md says the age gate "cannot be dismissed permanently" but does not say how age is established.
- **Safe reversible default:** A per-session self-declaration gate for categories with a country age rule, recorded in `consent_log`. There is no document verification and no storage of date of birth.
- **What depends on it:** Restricted category pages, redirect to merchants for age-restricted products.
- **Owner:** Compliance Lead with Legal.

### Decision (2026-09-26) · DECIDED — legal verification before production

- **Decided:** The default is adopted. A **per-session self-declaration** gate applies to categories with a country `minimum_age`, and it cannot be dismissed beyond the session. The declaration is recorded against the session with no date of birth and no document. Comparo sells nothing, and the merchant's checkout is where age is verified.
- **Rationale:** This is the least data collected that still informs the visitor and filters the display. It adds no identity-verification provider and no sensitive data.
- **Represented in:** `countries.minimum_age` (existing data). Planned config `comparo.compliance.age_gate` = `{method: 'self_declaration', scope: 'session'}`. The record's retention follows D-09.
- **Reversibility:** The method is config, and a verification provider can be added behind an interface.
- **Production prerequisite (human):** Legal confirms that self-declaration is adequate for each launch category in DE and AT. Where it is not, that category stays hidden in that market. **This requires legal verification before production launch.**

## D-13 · Delivery guarantee: deposits and payouts

- **Question:** Can Comparo hold merchant deposits (€400 / €900) and pay buyers shipping refunds and €5 credits from them? Under what legal structure (escrow, payment institution, set-off against invoices)?
- **Why it matters:** Holding third-party money for pass-through payments may be regulated activity. DELIVERY-GUARANTEE.md assumes it without naming a structure.
- **Safe reversible default:** Ship **Measured delivery** (free, measurement only). The Promise and Promise-48 h tiers are built but flag-disabled: no deposits collected, no claim payouts. Claims can be filed and are published as records only.
- **What depends on it:** `promise` add-ons, claims workflow, merchant invoices, guarantee page.
- **Owner:** CFO with Legal.

### Decision (2026-09-26) · DECIDED — legal verification before enabling

- **Decided:** The default is adopted. **Measured delivery** (free, measurement only) ships. The Promise and Promise-48 h tiers are built but flag-disabled: no deposits are collected and no claims are paid out. Claims can be filed and are published as records only.
- **Rationale:** Holding third-party money for payouts may be a regulated activity. Measurement alone carries no such risk.
- **Represented in:** Planned flags `delivery-promise` and `delivery-promise-48h` (both off).
- **Reversibility:** Flags.
- **Prerequisite for enabling (human):** A legal opinion on the structure (payment-services and escrow rules), CFO approval, and D-04/D-05. This is not a launch prerequisite because the feature is off.

## D-14 · Buyer subscriptions (Plus/Pro) and XP-for-Plus

- **Question:** Do Comparo Plus (€3.90) and Pro (€8.90) launch, and does 1,200 XP buy 30 days of Plus?
- **Why it matters:** Consumer billing brings OSS VAT, withdrawal rights and consumer contract terms. XP-for-entitlement makes points a quasi-currency.
- **Safe reversible default:** Buyer tiers are modelled as entitlements (`buyer_free|buyer_plus|buyer_pro`) but not sold. Every account resolves to `buyer_free`. XP accrues, and redeeming it is disabled. The free tier already has every trust feature (PAID-ADDONS), so nothing consumer-facing degrades.
- **What depends on it:** Alert capacity limits, history depth, exports, members-only rooms, D-04, D-05.
- **Owner:** Head of Product with CFO.

### Decision (2026-09-26) · DECIDED — legal verification before enabling

- **Decided:** The default is adopted. Buyer tiers exist as entitlements (`buyer_free|buyer_plus|buyer_pro`) but are **not sold**, and every account resolves to `buyer_free`. XP accrues, and **redemption is disabled** (C-31).
- **Rationale:** Consumer billing brings OSS VAT, withdrawal rights and consumer terms. The free tier already contains every trust feature.
- **Represented in:** Planned flags `buyer-subscriptions` and `xp-redemption` (both off).
- **Reversibility:** Flags.
- **Prerequisite for enabling (human):** D-04 (OSS), D-05, and consumer terms including withdrawal rights reviewed by Legal. This is not a launch prerequisite.

## D-15 · Source of order and delivery data

- **Question:** Where do order, delivery, return and dispute facts come from in production, given that "Shops do not give us order data"?
- **Why it matters:** Measured delivery, the returns index, delivery labels, promise eligibility and the Trust Score's shipping signal all read order rows (C-12). The prototype generates them.
- **Safe reversible default:** Orders are created only from evidence: an affiliate conversion, a verified proof, or a user declaration linked to a click. Delivery, return and dispute dates come from **shopper-reported events** (the CONTRIBUTIONS delivery-report source, capped and provisional for 48 h). Every derived figure keeps the 8-delivered-order minimum and shows its sample. No merchant order integration is assumed.
- **What depends on it:** `orders`, `order_events`, delivery stats, returns index, labels ("Measured delivery"), D-13.
- **Owner:** Head of Data with Head of Partnerships.

### Decision (2026-09-26) · DECIDED

- **Decided:** The default is adopted. Orders are created only from evidence: an affiliate conversion, a verified proof, or a user declaration linked to a click. Delivery, return and dispute dates come from shopper-reported events, which are capped and provisional for **48 h**. Every derived figure needs at least 8 delivered orders and shows its sample. No merchant order integration is assumed.
- **Rationale:** "Shops do not give us order data." Evidence-only orders avoid invented figures (invariant 7).
- **Represented in:** Phase 4 `orders` / `order_events` (in progress). Planned config `comparo.orders.shopper_report_provisional_hours` = 48, with the sample minimum from D-26.
- **Reversibility:** Config values. A merchant integration would be an additional source.
- **Production prerequisite:** None.

## D-16 · Which seeded Growth/Commercial analytics ship

- **Question:** Roughly a third of Growth OS and Commercial OS figures are scenario constants (AUDIT I.1, PROTOTYPE-LIMITATIONS). Which dashboards ship at launch?
- **Why it matters:** AUDIT's recurring defect class is a number that looks measured but is not. Shipping constants as metrics breaks the transparency principle.
- **Safe reversible default:** Only figures derivable from production tables are shown. Scenario inputs are shown only on a clearly labelled "Scenario" panel with their assumptions, and any widget without a data source is hidden. The forecast stays labelled as a projection (REVENUE-FORECAST).
- **What depends on it:** Growth OS and Commercial OS scope, analytics event pipeline priorities.
- **Owner:** Head of Product.

### Decision (2026-09-26) · DECIDED

- **Decided:** The default is adopted. Only figures derivable from production tables are shown. Scenario inputs appear only on a labelled "Scenario" panel that states its assumptions. A widget without a data source is hidden, and forecasts stay labelled as projections.
- **Rationale:** Invariant 7 (never fake completeness) and the AUDIT defect class of "a number that looks measured".
- **Represented in:** The Phase 12/13 dashboard presenters, enforced by presenter tests. No config.
- **Reversibility:** A widget appears once its data source exists.
- **Production prerequisite:** None.

## D-17 · Affiliate redirect: interstitial and commission disclosure

- **Question:** Does every outbound click go through a disclosure interstitial (prototype, AFFILIATE.md), or a direct 302 (API-ENDPOINTS)? Is a merchant's commission rate shown publicly?
- **Why it matters:** It affects conversion, the latency target, legal disclosure, and network terms that may forbid publishing negotiated rates (AFFILIATE-SALES says terms are not published unless deliberately disclosed).
- **Safe reversible default:** Keep the prototype interstitial: disclosure line, cookie window, an immediate "Continue" link, and auto-continue after 3 s with no urgency styling. The latency target applies to the 302 hop (C-29). Commission is shown as the **category range** from `seed-network.js`, and the merchant-specific rate is flag-disabled until partner contracts allow it.
- **What depends on it:** `/go` routes, click buffer, disclosure copy, network contracts.
- **Owner:** Head of Partnerships with Legal.

### Decision (2026-09-26) · DECIDED — legal verification before production

- **Decided:** The default is adopted. The disclosure interstitial stays: a disclosure line, the cookie window, an immediate "Continue" link, and auto-continue after **3 s** with no urgency styling. The countdown can be cancelled, which satisfies WCAG 2.2.1. The latency target applies to the 302 hop. Compliance and merchant suspension are re-checked before both hops (C-29). Commission is shown as the **category range**, and the merchant-specific rate is flag-disabled.
- **Rationale:** Transparent disclosure without showing negotiated rates that partner terms may forbid.
- **Represented in:** Phase 5 `/go` routes. Planned config `comparo.affiliate.interstitial_seconds` = 3 and planned flag `merchant-commission-display` (off).
- **Reversibility:** Config and flag.
- **Production prerequisite (human):** Legal approves the disclosure and advertising-labelling copy (DE/AT unfair-competition and advertising rules). Network terms decide whether rates may ever be shown. **This requires legal verification before production launch.**

## D-18 · Moderation legal framework (DSA) and binding juries

- **Question:** How do notice-and-action, statements of reasons, appeals and trusted-flagger duties map onto the moderation queue and the community juries? Are jury verdicts binding on Comparo?
- **Why it matters:** GOVERNANCE.md keeps one unilateral power (illegal content) and publishes the overturn rate. Platform-law duties may require structures the prototype does not model.
- **Safe reversible default:** Juries are **advisory**, and a named staff role records the final decision. That decision is always published with the jury result and a statement of reasons. Illegal-content removals are logged publicly within 24 h, as GOVERNANCE.md requires. Reporting and appeal flows capture the fields a statement of reasons needs.
- **What depends on it:** Moderation schema (reason codes, appeal state), jury tables, public takedown log.
- **Owner:** Legal with Head of Trust & Safety.

### Decision (2026-09-26) · DECIDED — legal verification before production

- **Decided:** The default is adopted. Juries are **advisory**. A named staff role records the final decision, which is always published with the jury result and a statement of reasons. Illegal-content removals are logged publicly within **24 h**. Notice, statement-of-reasons and appeal (internal complaint) flows capture the fields the DSA's notice-and-action, statement-of-reasons and complaint-handling provisions need.
- **Rationale:** Comparo keeps legal accountability for decisions, while the jury stays visible and meaningful.
- **Represented in:** A planned moderation schema (reason codes, appeal state) and planned config `comparo.governance.juries_binding` = `false` and `comparo.moderation.illegal_content_log_hours` = 24.
- **Reversibility:** Config values. Binding juries would need a new ADR.
- **Production prerequisite (human):** Legal confirms which DSA obligations apply to the operator (including any size-based exemptions), names the point of contact, and approves the notice and appeal copy. **This requires legal verification before production launch.**

## D-19 · Importing third-party review counts / ratings

- **Question:** Should shop pages show externally sourced review populations (the prototype's "Reviews 3,421" next to "On Comparo 13 weighted")?
- **Why it matters:** DATABASE forbids seeded aggregates, and the prototype blends them (C-14). Imported ratings need a licence and a source.
- **Safe reversible default:** No imports. Ratings are computed only from reviews held on Comparo, and a shop with few reviews shows the count and "limited data".
- **What depends on it:** Shop profile, `AggregateRating` markup, Trust Score review input.
- **Owner:** Head of Trust & Safety.

### Decision (2026-09-26) · DECIDED

- **Decided:** The default is adopted. There are **no imports**. Ratings are computed only from reviews held on Comparo. A shop with few reviews shows the count and "limited data", and `AggregateRating` markup is emitted only from our own reviews above the D-26 minimum.
- **Rationale:** DATABASE forbids seeded aggregates (C-14). Importing ratings needs a licence and a named source.
- **Represented in:** No import code exists. No flag is needed.
- **Reversibility:** Imports would need a new ADR and a licence.
- **Production prerequisite:** None.

## D-20 · One placement inventory and ad pricing model

- **Question:** How are the commercial placements (13), the visibility surfaces (12) and the six campaign-marketplace products merged into one sellable catalogue, and which price model applies (C-26)?
- **Why it matters:** Two parallel inventories mean double-selling and two prices for the same slot.
- **Safe reversible default:** Visibility surfaces are the one inventory, and their rules (slots, holdback 20 %, caps, gates, position 1 never sold) are enforced. Prices are computed by the visibility formula and shown only to approved shops. The CPM/CPC model is recorded as the billing basis. Self-serve booking is **disabled** until D-04/D-05, and staff book manually.
- **What depends on it:** Advertising page, campaign approval, invoicing of delivered spend.
- **Owner:** Head of Commercial.

### Decision (2026-09-26) · DECIDED — legal verification before enabling

- **Decided:** The default is adopted. The visibility surfaces are the **one inventory**. Their rules are enforced: slots, 20 % holdback, caps, gates, and position 1 is never sold. Prices come from the visibility formula and are shown only to approved shops. CPM/CPC is the billing basis. **Self-serve booking is disabled**, and staff book manually. No commercial data reaches ranking (invariant 1, ADR-0004).
- **Rationale:** One inventory prevents double-selling. Manual booking fits billing that does not charge yet (D-05).
- **Represented in:** Phase 13. Planned config `comparo.visibility.holdback_ratio` = 0.2 and planned flag `self-serve-ad-booking` (off).
- **Reversibility:** Config and flag.
- **Prerequisite before the first paid placement (human):** Legal approves the sponsored/ad labelling (DSA advertising transparency and national advertising law), plus D-04/D-05.

## D-21 · Scope of the commercial gate (`/developers`)

- **Question:** Should API and data-product pricing (`/developers`) sit behind an approved *shop* profile, as in the prototype, when data customers are usually not shops?
- **Why it matters:** The gate logic is right for shops buying visibility, but it may block data sales.
- **Safe reversible default:** Keep the prototype behaviour (gate on every commercial route), with a flag to exempt `/developers` once an alternative "data customer" application exists. The Developer (free) API tier stays self-serve.
- **What depends on it:** API plan sign-up, data-product sales funnel.
- **Owner:** Head of Commercial.

### Decision (2026-09-26) · DECIDED

- **Decided:** The default is adopted. The commercial gate stays on every commercial route, including `/developers` pricing. A flag can exempt `/developers` once a "data customer" application exists. The free Developer API tier stays self-serve.
- **Rationale:** This is the prototype behaviour and the smallest exposure. Data sales have no process yet.
- **Represented in:** Planned flag `developers-gate-exempt` (off).
- **Reversibility:** A flag.
- **Production prerequisite:** None.

## D-22 · Do returns and measured delivery feed the Trust Score

- **Question:** Should the returns index and measured on-time delivery replace the seeded shipping signals in Trust Score 2.0 (ORDERS.md "Still open")?
- **Why it matters:** Changing trust inputs moves every shop's score and ranking position.
- **Safe reversible default:** Trust uses measured delivery where the sample is at least 8 (same weight as the seeded signal it replaces) and the code fallback otherwise, marked "not measured" (C-15). Returns are **not** a trust input until this owner decides and publishes a methodology change.
- **What depends on it:** TrustService, trust history continuity, methodology page.
- **Owner:** Methodology owner.

### Decision (2026-09-26) · DECIDED

- **Decided:** The default is adopted. Measured delivery replaces the seeded shipping signal where the sample is at least **8**, at the same weight. Otherwise the code fallback applies, marked "not measured" (C-15). Returns are **not** a trust input in v1. Any change goes through the D-10 methodology changelog.
- **Rationale:** This moves no shop's score without a published methodology change.
- **Represented in:** A planned TrustService input. Planned config `comparo.trust.returns_as_input` = `false`, with the sample from `comparo.thresholds.delivered_orders` (D-26).
- **Reversibility:** Config plus a changelog entry.
- **Production prerequisite:** None.

## D-23 · Device fingerprints and fraud-signal consent

- **Question:** Which request signals may be used for fraud and referral abuse, under which legal basis, and do they need consent?
- **Why it matters:** FRAUD and REFERRALS use "shared device fingerprint", while AFFILIATE forbids keeping one beyond attribution (C-36).
- **Safe reversible default:** Server-side salted, rotating hashes of a truncated IP and the user-agent class only. No client-side fingerprinting script. Retention follows D-09. Fraud rules that need more are disabled.
- **What depends on it:** Review-trust penalties, referral abuse detection, click dedup.
- **Owner:** DPO.

### Decision (2026-09-26) · DECIDED — legal verification before production

- **Decided:** The default is adopted. Only server-side, salted, rotating hashes are used: the IP is truncated before hashing (IPv4 /24, IPv6 /48), plus the user-agent class. There is **no client-side fingerprinting script**. Fraud hashes rotate their salt every 30 days and are nulled after 90 days (D-09), and search analytics keep their daily rotation (A-24). Fraud rules that need more than this stay disabled. The working legal basis is legitimate interest in fraud prevention, documented in a legitimate-interest assessment.
- **Rationale:** This is the most privacy-preserving reading (C-36). It reads nothing from the device, so no consent is needed for storage or access on the terminal.
- **Represented in:** A planned `RequestFingerprint` value object and planned config `comparo.fraud.client_fingerprinting` = `false` and `comparo.fraud.hash_salt_rotation_days` = 30. Retention is `comparo.retention.request_hash_days` (D-09).
- **Reversibility:** Config values.
- **Production prerequisite (human):** The DPO confirms the legal basis and the ePrivacy/TDDDG assessment. **This requires DPO verification before production launch.**

## D-24 · Default market for anonymous visitors and crawlers

- **Question:** Which market prices a page when there is no user selection, account default or IP hint (the COMPLIANCE cascade end), and is IP geolocation used at all?
- **Why it matters:** INDEXING forbids silently redirecting crawlers. The public API returns 422 without a country (C-22).
- **Safe reversible default:** Default market **DE**, stated on the page ("Prices for delivery to Germany — change"). IP country is used only as a *suggestion* banner, never as an automatic switch.
- **What depends on it:** SSR caching keys, canonical content, structured data offers.
- **Owner:** Head of SEO with Head of Growth.

### Decision (2026-09-26) · DECIDED

- **Decided:** The default market is **DE**, stated on the page ("Prices for delivery to Germany — change"). Crawlers get DE with no redirect. The public API keeps its 422 response without a country (C-22). The IP-country suggestion banner is **off** until a geolocation source is chosen, and it never switches automatically.
- **Rationale:** INDEXING forbids silently redirecting crawlers. No third-party geolocation service is contracted.
- **Represented in:** Existing `comparo.default_market` (env `COMPARO_DEFAULT_MARKET`, `DE`) and `comparo.market_cookie`. Planned config `comparo.markets.ip_suggestion` = `false`.
- **Reversibility:** Config values.
- **Production prerequisite:** None. A geolocation database would need a licence (human), but only if the banner is enabled.

## D-25 · Offer expiry window

- **Question:** When does a stale offer stop being shown at all? DATA-QUALITY says expired after 7 days, and the code never hides offers by age (C-25).
- **Why it matters:** Publishing week-old prices on a price-comparison site undermines the core promise. Hiding too early empties tables for slow feeds.
- **Safe reversible default:** Keep the code behaviour: stock shows "Unknown" after 48 h, and ranking applies the −10 stale penalty. An offer is additionally **deactivated** when it is missing from two consecutive successful feed runs of its merchant, or not seen for 7 days, whichever comes first. Both are config values.
- **What depends on it:** Feed diff/commit step, offer counts, sitemap and indexability rules.
- **Owner:** Head of Data.

### Decision (2026-09-26) · DECIDED

- **Decided:** The default is adopted and already implemented (A-10). Stock shows "Unknown" after 48 h, and ranking applies the −10 stale penalty. An offer is deactivated after 2 consecutive published runs without it, or after 7 days unseen. Runs that would deactivate more than 50 % of listings hold deactivation. The code's freshness thresholds (stock 6/24/48 h, price fresh < 24 h, stale > 48 h) move from constants into config, with the same values.
- **Rationale:** This avoids publishing week-old prices without emptying tables for slow feeds.
- **Represented in:** Existing `comparo.feeds.missing_runs_before_deactivation` (2), `unseen_days_before_deactivation` (7), `mass_removal_ratio` (0.5) and `mass_removal_min_offers` (10). Planned `comparo.offers.freshness` = `{stock_high_hours: 6, stock_good_hours: 24, stale_hours: 48, price_fresh_hours: 24}`, replacing the literal `48` in `RankingService`.
- **Reversibility:** Config values.
- **Production prerequisite:** None.

## D-26 · Minimum-sample thresholds

- **Question:** Who owns the publication thresholds: 8 delivered orders (delivery, returns, labels), 12 coupon reports, 300 demand pledges, 5/4 reviews for labels, 3 returns for fault scoring?
- **Why it matters:** They decide what is published at all. AUDIT K records thresholds re-pitched because labels were unearnable on seed data. Production data volumes will differ.
- **Safe reversible default:** Every threshold is a named config value set to the prototype number. Any change goes through the methodology changelog (D-10 process), and the published criteria copy is interpolated from config.
- **What depends on it:** Labels, delivery stats, coupon success rates, demand signals, returns index.
- **Owner:** Methodology owner.

### Decision (2026-09-26) · DECIDED

- **Decided:** The default is adopted. Every threshold is a named config value set to the prototype number: 8 delivered orders, 12 coupon reports, 300 demand pledges, the 5/4 review minimums for labels and 3 returns for fault scoring. Any change goes through the methodology changelog (D-10), and the published criteria copy is interpolated from config.
- **Rationale:** Thresholds decide what is published at all, so they must be visible and change only through the changelog.
- **Represented in:** Existing `comparo.search.analytics.min_demand_sessions` (3). Planned `comparo.thresholds.*`: `delivered_orders` 8, `coupon_reports` 12, `demand_pledges` 300, label review minimums 5 and 4 (names per the label spec) and `returns_fault` 3.
- **Reversibility:** Config values plus a changelog entry.
- **Production prerequisite (human):** A methodology owner (see D-10).

## D-27 · Transactional and inbound email provider

- **Question:** Which provider handles transactional mail and **inbound parsing** for the forwarded-confirmation verification route (personal `verify+…@` addresses)?
- **Why it matters:** The email route needs inbound webhooks and a dedicated domain. BACKEND-MIGRATION suggests Postmark or Mailgun without choosing.
- **Safe reversible default:** Laravel mail through a provider-agnostic driver for outbound. The forwarded-email verification route is **disabled**. Click-match and receipt upload are the launch routes, so no inbound mail infrastructure is needed on day one.
- **What depends on it:** Verification route 2, digest and alert emails, bounce handling.
- **Owner:** CTO.

### Decision (2026-09-26) · DECIDED

- **Decided:** Outbound mail uses Laravel mail through a provider-agnostic driver. The **forwarded-email verification route is disabled**, so click-match and receipt upload are the launch routes and no inbound mail infrastructure is needed. The provider is chosen at contract time against these requirements: EU data processing, a DPA, SPF/DKIM/DMARC on a dedicated sending domain, and a signed inbound-parsing webhook for later.
- **Rationale:** No inbound infrastructure is needed on day one. Any mailer driver works.
- **Represented in:** `config/mail.php` (`MAIL_MAILER`). Planned flag `verification-forwarded-email` (off).
- **Reversibility:** The driver is env config, and the route is a flag.
- **Production prerequisite (human):** A provider account and DPA, DNS records on the sending domain, and credentials in a secret store.

## D-28 · Live rooms at launch and real-time transport

- **Question:** Do the 38 live rooms (price events as messages, presence, slow mode) launch, and on what transport?
- **Why it matters:** Real-time infrastructure, moderation load and abuse surface. LIVE-ROOMS.md says a socket replaces the BroadcastChannel transport.
- **Safe reversible default:** Rooms are disabled by feature flag. Promotion-into-topic and price-event records are stored, so enabling rooms later has history to show. When enabled, use Laravel's first-party WebSocket server behind the same moderation pipeline.
- **What depends on it:** Community scope, moderation staffing (D-08), desk-hour advertising format.
- **Owner:** Head of Product with CTO.

### Decision (2026-09-26) · DECIDED

- **Decided:** The default is adopted. Rooms are disabled by flag. Promotion-into-topic and price-event records are stored, so enabling rooms later has history to show. When rooms are enabled, they use Laravel's first-party WebSocket server (Reverb) behind the same moderation pipeline.
- **Rationale:** Real-time moderation load and abuse surface are not staffed at launch (D-08).
- **Represented in:** Planned flag `live-rooms` (off).
- **Reversibility:** A flag.
- **Prerequisite for enabling:** Adding Reverb is a dependency change that needs approval (AGENTS.md), and moderation staffing (D-08) is required.

## D-29 · Permissions not present in the prototype

- **Question:** Confirm the three new permissions (`pricing.review`, `growth.view`, `commercial.view`) and the merge of the duplicate roles "Affiliate Manager" and "Analyst" (C-20).
- **Why it matters:** Least privilege for staff consoles. Without them, Growth/Commercial read access would ride on broader permissions.
- **Safe reversible default:** Create the three permissions and grant them only to Super Admin and the matching commercial or growth roles. Merged roles get the **intersection** of the two prototype permission sets until the owner approves the union.
- **What depends on it:** Staff RBAC seeder, policy tests, audit coverage.
- **Owner:** CTO with Head of Operations.

### Decision (2026-09-26) · DECIDED (amended default)

- **Decided:** `pricing.review` is implemented as the existing **`pricing.anomalies.review`**, so no duplicate permission is added. `growth.view` and `commercial.view` are confirmed and already exist in `Permission`. **Amended rule for merged roles:** they get the **union of the read-only permissions and the intersection of the write permissions** from the two prototype role definitions. That replaces "intersection until the owner approves the union", now that the owner has delegated the decision. The current `StaffRole` grants already conform. Affiliate Manager keeps `affiliate.manage` (in both prototype sets) plus read access. Analyst is read-only (catalogue, merchants, analytics, growth, affiliate reports, commercial). The new `ranking.publish` permission comes from D-10.
- **Rationale:** Least privilege for writes. A read-only analyst role is useful without extra risk, and ranking never reads commercial data (invariant 1).
- **Represented in:** `App\Domain\Accounts\Authorization\Permission` and `StaffRole`, covered by `tests/Feature/Authorization/StaffAuthorizationTest.php`.
- **Reversibility:** A seeder or enum change covered by policy tests.
- **Production prerequisite (human):** Assign named staff to roles.
