# Pre-backend readiness audit

Assessment of whether the prototype is complete enough to start Laravel development against, and what
must be decided before the first migration is written.

**Verdict: yes for the read model, no for the write model.** Every screen a Laravel app would need to
serve exists and is populated; what is missing is a handful of entities that the prototype currently
*infers* rather than stores. Those gaps are listed in §4 and each one would otherwise become a
schema decision made by accident.

---

## 1. Route inventory — measured, not assumed

Every route below was loaded and its rendered `<main>` measured in this audit run.

### Public commerce
| Route | Renders | Notes |
|---|---|---|
| `#/` | 6,961 chars | 11 sections, 9 with rules and eyebrow labels |
| `#/search?q=…` | 6,263 | intent detection, facets, zero-result logging |
| `#/products/<slug>` | 12,488 | the heaviest view: offers, ComparoRank, history, reviews, Q&A |
| `#/compare` | 277 | correct empty state (0 products) |
| `#/basket` | 617 | correct empty state |
| `#/shops` · `#/shops/<slug>` | 3,799 · 2,246 | index + profile with trust breakdown |
| `#/brands` · `#/brands/<slug>` | 1,614 · ok | |
| `#/categories` · `#/categories/<slug>` | 1,327 · 2,215 | index built this round; 16 product links per category |
| `#/ingredients` | 3,587 | |
| `#/deals` | 3,187 | six tabs, all reachable from the nav |
| `#/reviews` | 5,603 | split into product vs shop universes |
| `#/ask` | 507 | answer engine shell |
| `#/countries/<slug>` | 1,522–1,572 | four markets verified distinct |

### Community
`#/community` 5,349 · `#/forum` 4,456 · `#/forum/<cat>` · `#/forum/topic/<slug>` 2,537 ·
`#/guides` 2,035 · `#/users/<handle>` 1,982 · `#/trust` 2,882 · `#/methodology` 3,038 · `#/blog` 1,509

### Account & business
`#/account` (tabbed: saved, watchlist, alerts, reviews, notifications, privacy) · `#/saved` ·
`#/for-you` 1,512 · `#/merchant` · `#/for-merchants` · `#/creators` · `#/newsletter`

### Staff
`#/admin` · `#/seo` (194 pages registry) · `#/intel` · `#/growth` (17 tabs) · `#/commercial` ·
`#/design-system`

**Thin routes are legitimate empty states or tab shells, not defects** — verified individually
(`#/compare` says "Nothing in the comparison yet", `#/account` is a tab bar with counts).

### Documented as missing
`#/for-merchants/pricing`, `#/developers`, `#/advertising` — content exists as builder data
(`cm.planMatrix()`, `cx.apiPlans`, `cx.dataProducts`) but no route renders it.

---

## 2. Mechanical sweeps

| Sweep | Result |
|---|---|
| Dead controls (buttons with no `onClick`/`onChange`) | **0 of 231** across home, product, deals, community, reviews, admin, SEO, intel, growth, commercial, merchant |
| Console errors | clean |
| Unresolved template holes | 1 found and fixed this run (`t.replies` on the For-you feed — the personalisation engine returns `slug/title/why`, so the count is now derived from the thread record) |
| Unresolved CSS custom properties | 0 of 51 defined |
| Page-level horizontal overflow | none |
| Clipped/unreachable grids at 320–390px | 0 |
| Internal links collected | 104 unique from three routes alone; sampled targets all resolve |

---

## 3. What maps cleanly to Laravel

The prototype was built as pure functions over a seed graph, which is why the mapping is mechanical.

### Tables (from the seed shape)
`countries` · `currencies` · `categories` · `brands` · `products` · `product_variants` ·
`ingredients` · `ingredient_product` · `merchants` · `merchant_zones` · `offers` · `coupons` ·
`price_snapshots` · `reviews` · `review_replies` · `users` · `badges` · `forum_categories` ·
`forum_threads` · `forum_replies` · `guides` · `community_deals` · `lists` · `follows` · `alerts` ·
`notifications` · `affiliate_clicks` · `affiliate_programs` · `feeds` · `feed_runs` ·
`compliance_rules` · `audit_log` · `merchant_prospects` · `creators` · `campaigns` ·
`content_opportunities` · `newsletters` · `research_stories` · `experiments` · `growth_tasks` ·
`subscriptions` · `invoices` · `invoice_items` · `sponsored_placements` · `api_keys` · `api_usage` ·
`disputes` · `support_tickets`

### Services (each is already one file or one block of pure functions)
| Prototype | Laravel service |
|---|---|
| `intel.js → rank()` | `RankingService` |
| `intel.js → trust()`, `riskScore()` | `TrustService`, `RiskService` |
| `intel.js → reviewTrust()`, fraud signals | `FraudService` |
| `intel.js → match()`, clustering | `MatchingService` |
| `intel.js → priceStats()`, anomalies, confidence | `PricingService` |
| `intel.js → personal()`, `related()` | `RecommendationService` |
| `intel.js → affiliateMetrics()` | `AffiliateService` |
| `intel.js → automations` | `AutomationService` |
| `growth.js` | `GrowthService` + 8 sub-services (see GROWTH-OS.md §Future services) |
| `commercial.js` | `BillingService`, `SubscriptionService`, `SponsoredService`, `ApiPlanService` |

### Jobs / queues
`ImportFeed` · `MatchOffers` · `AggregatePrices` · `DetectAnomalies` · `ScoreTrust` ·
`EvaluateAlerts` · `SendDigest` · `GenerateSitemaps` · `RunAutomations` · `BillInvoices` ·
`RecalculateComparoRank`

### Events
`OfferUpdated` · `ReviewCreated` · `ReviewVerified` · `MerchantRiskChanged` · `ProductMatched` ·
`DealExpired` · `InvoicePaid` · `SubscriptionRenewed` · `AlertTriggered` · `ProofSubmitted`

### Auth & authorisation
Roles and granular permissions already exist as data (`cx.roles`, `comCan()`, `requirePerm()`), so
they translate to Laravel policies almost literally. The role switcher in `/intel → Roles` is a demo
control — a real deployment binds the role to the authenticated user.

---

## 4. Must be decided before the first migration

These are the places where the prototype infers something a database must store. Each one is a
schema decision that will otherwise be made by accident.

1. **`orders` does not exist.** Purchase verification, delivery reliability, disputes and returns all
   currently infer a purchase from a redirect or a receipt. A minimal `orders` table (user, merchant,
   product, total, currency, ordered_at, source, proof_id) anchors all four and removes three seeded
   figures. **This is the single most important missing table.**
2. **`purchase_proofs` is prototype-only.** `state.proofs` records route, status and evidence but has
   no moderation surface, so a pending receipt can never be confirmed. Needs the table *and* a
   moderation queue endpoint.
3. **Review weighting is claimed but not implemented.** `ix.reviewTrust()` scores every review and the
   docs say unverified reviews weigh less, but `ratingOf()` averages all approved reviews equally.
   Decide the weighting function before ratings are persisted, because changing it later rewrites
   every aggregate.
4. **Price history granularity.** Product-level daily history exists; per-merchant history covers only
   a subset. Fake-discount detection and merchant price-accuracy scoring both reason from partial
   series today. Decide whether `price_snapshots` is per offer (correct, larger) or per product
   (cheaper, weaker).
5. **Ingredient dosing.** `ingredient_product` links substances to products without amounts, so
   "price per gram of active" — the strongest comparison axis in this category and a genuine moat —
   is impossible. Add `amount` + `unit` + `per_serving` now; retrofitting means re-entering the
   catalogue.
6. **Stock history.** Stock confidence derives from feed freshness alone, so a flickering offer looks
   identical to a stable one. Either store `offer_stock_events` or accept the weaker signal explicitly.
7. **Alert evaluation.** Alerts persist but nothing evaluates them; the "3 alerts triggered" figures
   are seeded. The `EvaluateAlerts` job and an `alert_triggers` table are needed for the notification
   centre, the digest and the action strips to become derived rather than decorative.
8. **Locale strategy.** The language switcher persists a preference and changes nothing. Decide
   whether translation is UI-only (Laravel lang files) or extends to content (translated product
   copy, per-locale routes) — this determines whether `hreflang` output stays synthetic.

---

## 5. Known incomplete mechanisms (safe to build around, but do not mistake for done)

* **Creator coupons are not real coupons** — issuing one audits and toasts but never enters
  `allCoupons()`, so a creator code cannot be redeemed on a deal page.
* **Community reports do not move data confidence** — reports are recorded; `priceConfidence()` does
  not read them, though DATA-QUALITY.md describes the link.
* **Basket compare ignores ComparoRank** — it optimises on total price and shipping, offering trust as
  a separate option rather than a rank-weighted one.
* **Growth and commercial tasks share one list and one vocabulary**, though the docs describe distinct
  sources.
* **Roughly a third of Growth/Commercial analytics are seed constants**, not derived: channel users,
  cohort retention, page-type revenue, assisted conversions, delivery reliability. Every one is a
  future contradiction — see §7.

---

## 6. Modern & clear — where the interface stands

* **Navigation**: four public mega-dropdown groups; every role destination lives in the account
  dropdown (For you, saved, alerts, reviews, profile, settings, plus Merchant or Staff consoles by
  role) — verified rendering `A:For you` + 5 `BUTTON[aria-expanded]`.
* **Topbar**: one minimal segmented control (country · currency · language) with native select chrome
  stripped, plus ⌘K and theme. Locale links removed.
* **Responsive**: seven documented patterns (RESPONSIVE.md); consumer tables collapse to card lists
  with a Details disclosure, admin grids scroll with an edge fade, panel heights are measured at
  runtime against the fixed bottom bar.
* **Accessibility**: WCAG AA targets, 0 unlabelled icon controls, `role="table"` on grid tables,
  `aria-expanded`/`aria-pressed` on disclosures and toggles, Escape closing every overlay, toast
  contrast 17.19:1 light / 17.41:1 dark, `prefers-reduced-motion` respected.
* **State**: schema version 3 with migrations, corruption recovery, scoped reset, multi-tab detection,
  debounced writes.

---

## 7. The one rule to carry into the backend

The recurring defect class through this entire build — eight consecutive review rounds — was **a
display string written next to the record it describes**. Campaign names, past-due alerts, API
allowances, invoice overage lines, credit-note reasons, plan catalogues, invoice ledgers, renewal
days, campaign pacing, revenue concentration, nav market links, and the For-you reply count fixed in
this very audit run: every one was a number or a name typed into copy instead of interpolated from its
entity.

In Laravel this class becomes cached counters, denormalised columns and notification bodies that
drift from the rows they summarise.

**Rule: if a number or a name appears in output, derive it from the entity at read time — or, if it
must be denormalised for performance, make the job that writes it the only writer and give it a test
that compares it against the source of truth.**

---

## 8. Recommended sequence for backend work

1. **Schema first, with §4 decided** — especially `orders`, `purchase_proofs`, snapshot granularity
   and ingredient dosing. These four shape everything downstream.
2. **Read model + catalogue import** — products, merchants, offers, feeds, then the pricing pipeline
   (`AggregatePrices`, `DetectAnomalies`). At this point the existing frontend can be pointed at real
   endpoints screen by screen, because every view is already a pure function of this data.
3. **Scoring services** — Ranking, Trust, Matching, Pricing as queued recalculations writing to score
   tables, so the browser receives precomputed values instead of computing them (PERFORMANCE.md).
4. **Identity, roles, policies** — bind the existing permission map to real users.
5. **Write flows** — reviews with the verification loop *closed* (proof → moderation → weighted
   aggregate), community posting, alerts with real evaluation.
6. **Commercial** — subscriptions, invoicing, sponsored inventory, API plans and metering.
7. **Growth** — opportunity generation as scheduled jobs over the live graph.

Steps 1–3 can proceed immediately. Step 5 is blocked on the §4.1–4.3 decisions, and nothing else is.
