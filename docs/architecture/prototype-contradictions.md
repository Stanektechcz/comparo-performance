# Prototype contradictions — reconciled

Status: reconciliation baseline for the Laravel 13 + PostgreSQL 16 + Inertia/React (SSR) modular monolith.
Scope: every root `*.md` spec document, checked against the executable prototype
(`Comparo Performance.dc.html`, `seed*.js`, `intel.js`, `growth.js`, `commercial.js`, `visibility.js`,
`governance.js`, `labels.js`, `live.js`, `gamify.js`, `addons.js`). `AGENTS.md` is Laravel Boost tooling
guidance for the new app, not a prototype spec, and is out of scope.

## How conflicts were resolved

Source-of-truth priority, highest first. The **Why** line of each block cites these numbers.

| # | Source |
|---|---|
| T | Fixed target decisions: Laravel full-stack (Inertia React SSR), PostgreSQL 16, Redis, Horizon, Scout + Meilisearch, money as integer minor units, append-only per-offer price history, ranking never reads commission/plan/commercial spend, compliance evaluated server-side before serialisation |
| 1 | Working prototype code (`Comparo Performance.dc.html` and the engine files) |
| 2 | Newest seed extension files (`seed-geo`, `seed-live`, `seed-gamify`, `seed-dose`, `seed-orders`, `seed-labels`, `seed-network`, `seed-visibility`, `seed-governance`, `seed-addons`) |
| 3 | `AUDIT.md` |
| 4 | `BACKEND-READINESS.md` |
| 5 | Domain docs (COMPARORANK, TRUST-SCORING, ORDERS, DOSING, VISIBILITY, …) |
| 6 | `DATABASE.md`, `API-ENDPOINTS.md` (and `API.md`) |
| 7 | `ARCHITECTURE.md` |
| 8 | Older notes in `BACKEND-MIGRATION.md` |

Target decisions (T) override everything. If code conflicts with a T rule, for example code that lets a
commercial property reach a consumer ordering, the code is treated as a defect of the class AUDIT §E/§7
already tracks. It is not treated as the specification.

Quotes are limited to 20 words. Code quotes are verbatim fragments. Blocks are ordered by backend
impact, highest first.

## Index

| ID | Conflict | Impact |
|---|---|---|
| C-01 | Tech stack: Next.js / Vue / API-first vs Laravel Inertia React | High |
| C-02 | Money representation | High |
| C-03 | Stale "missing entity" claims (orders, proofs, dosing, weighting) | High |
| C-04 | Price history granularity and basis | High |
| C-05 | Compliance: missing-rule default and `unknown` semantics | High |
| C-06 | Commercial signals reaching consumer surfaces | High |
| C-07 | Sponsored / promoted rows vs organic order | High |
| C-08 | ComparoRank port: formula, cache key, tie-break, fallbacks | High |
| C-09 | Two Trust Score formulas | High |
| C-10 | Two price-badge / price-statistics implementations | High |
| C-11 | Market count: 12 "European" vs 27, US included | High |
| C-12 | Orders: "we process no orders" vs a full order record | High |
| C-13 | Purchase-verification vocabulary and proof keying | High |
| C-14 | Rating aggregation | High |
| C-15 | Trust inputs: silent defaults, seeded vs measured delivery | Medium-High |
| C-16 | Plan and tier vocabularies | Medium-High |
| C-17 | Enum / status naming | Medium-High |
| C-18 | Endpoints mapped to prototype functions that do not exist | Medium |
| C-19 | Route naming | Medium |
| C-20 | Permissions and roles | Medium |
| C-21 | API conventions (errors, pagination, money shape) | Medium |
| C-22 | Mandatory country vs crawlable default page | Medium |
| C-23 | Matching-engine scales and bands | Medium |
| C-24 | Price-anomaly thresholds and flag source | Medium |
| C-25 | Freshness / stale thresholds | Medium |
| C-26 | Three placement inventories, two pricing models, two cap sets | Medium |
| C-27 | Public commercial pages vs commercial gate | Medium |
| C-28 | API volume: entitlement quota vs API plan; rate limits | Medium |
| C-29 | Affiliate redirect: interstitial, latency, compliance, commission display | Medium |
| C-30 | Conversion webhook path and network list | Medium |
| C-31 | Three points currencies (reputation, XP, Comparo points) | Medium |
| C-32 | Jury panel size | Low-Medium |
| C-33 | Locale strategy | Low-Medium |
| C-34 | Retention of proof documents | Low-Medium |
| C-35 | Append-only audit log vs GDPR erasure | Low-Medium |
| C-36 | Device fingerprints | Low-Medium |
| C-37 | Dosing schema | Low-Medium |
| C-38 | Price alerts: shape and evaluation | Low-Medium |
| C-39 | Price-confidence scale | Low |
| C-40 | Two "unverified reference price" definitions | Low |
| C-41 | Review sub-rating dimensions | Low |
| C-42 | Seed load order | Low |
| C-43 | Creator coupons are not coupons | Low |
| C-44 | Housekeeping: `support.js`, demo accounts | Low |

---

## C-01 · Tech stack: Next.js / Vue / API-first vs Laravel Inertia React

- **Source A:** `ARCHITECTURE.md`: "Next.js web │  SSR/ISR pages, hreflang routing, structured data". Also "every read goes through the API or the search index".
- **Source B:** `BACKEND-MIGRATION.md`: "| Framework | Laravel 11 |" and "Inertia + Vue, or keep the SPA and serve JSON". `API-ENDPOINTS.md`: "Auth: Sanctum for the SPA". `API-ENDPOINTS.md` / `KNOWLEDGE-GRAPH.md`: "Meilisearch or Typesense".
- **Chosen interpretation:** Laravel 13 full-stack. Web pages are Inertia React pages rendered with SSR, and there is no separate Next.js tier and no ISR. Each prototype `build*()` view model becomes one controller or action that returns Inertia props. `/api/v1` exists only for the merchant API, the monetised public read API and webhooks. It uses Sanctum tokens, not SPA auth. Search uses Scout + Meilisearch; Typesense is dropped. Queues use Horizon on Redis.
- **Why:** T overrides 6, 7 and 8. The docs disagree with each other anyway (Next.js in 7, Vue or SPA in 8), so neither one is a spec.
- **Backend impact:** Web auth is session-based, and CSRF is handled by Laravel. Structured data, `<head>`, canonical and hreflang are rendered server-side in the Inertia root and page `Head`. This replaces `applyHead()`. Caching is HTTP and CDN caching plus Redis tags, not ISR revalidation. The internal service endpoints in ARCHITECTURE (`/internal/rank`, `/internal/match`, and so on) become in-process service classes, not HTTP endpoints.

## C-02 · Money representation

- **Source A:** `DATABASE.md`: "Money is `numeric(12,2)` plus a currency code; never floats." `API.md`: `{ "amount": 39.9, "currency": "EUR" }`.
- **Source B:** `API-ENDPOINTS.md`: `"price": { "amount": 4390, "currency": "EUR" },   // minor units, always`. The code (`seed.js`) stores float EUR and converts for display: `const v = eur * c.rate;`. It shows 0 decimals for CZK/SEK/PLN. The HTML methodology says "Internal prices are stored in EUR and converted at display time using the daily rate list."
- **Chosen interpretation:** Money is stored as a `bigint` amount in the currency's ISO-4217 minor units plus a `char(3)` currency. The offer price is stored in the currency the merchant feed supplies. The EUR-normalised value is used for ranking and aggregates. It is derived through a dated FX rate and never stored as the only value. API money is `{amount:int, currency}`. Display rounding (for example 0 decimals for CZK) is a presentation rule and never a storage rule.
- **Why:** T. API-ENDPOINTS (6) agrees. DATABASE (6) and API.md lose to T. The code's float EUR is a prototype simplification that T overrides.
- **Backend impact:** A Money value object and an Eloquent cast are needed everywhere. Every `numeric(12,2)` column in DATABASE.md becomes `bigint *_minor` plus `currency`. Score formulas are ratio-based, so ComparoRank, Deal Score and the badges are unchanged once both sides share a currency. FX snapshots need a table (`fx_rates(date, base, quote, rate)`). The source is open decision D-06.

## C-03 · Stale "missing entity" claims (orders, proofs, dosing, weighting)

- **Source A:** `BACKEND-READINESS.md` §4: "**`orders` does not exist.**", "`purchase_proofs` is prototype-only.", "`ratingOf()` averages all approved reviews equally." `BACKEND-MIGRATION.md`: "**No `Order` entity.**", "**Ingredients carry no dose.**" BACKEND-READINESS §5: "**Community reports do not move data confidence**".
- **Source B:** `AUDIT.md` §C: "~~**No order entity.**~~ **CLOSED**". Its dosing entry is also marked **CLOSED**. B1 is **CLOSED** (proof queue), B3 is **CLOSED** (weighted rating) and B4 is **CLOSED** (`confidenceOf`). The code has `seed-orders.js`, `seed-dose.js`, `pendingProofs()`/`resolveProof()`, `reviewWeight()` and `confidenceOf()`.
- **Chosen interpretation:** Orders, purchase proofs with a moderation queue, ingredient doses, credibility-weighted ratings and report-adjusted price confidence are all in the v1 specification. Still open, and so still to be decided: stock history (§4.6), alert evaluation (AUDIT B8), locale strategy (B9) and creator coupons (B7).
- **Why:** Code (1) and newest seeds (2) exist, and AUDIT (3) records each gap as closed. That outranks BACKEND-READINESS (4) and BACKEND-MIGRATION (8).
- **Backend impact:** The first migration set must include `orders`, `purchase_proofs`, `product_ingredient.amount_mg/is_carrier/nrv_mg`, `reviews.weight` and `offer_reports`. BACKEND-READINESS §8 says "Step 5 is blocked on §4.1–4.3". That block is resolved.

## C-04 · Price history granularity and basis

- **Source A:** Code, `seed.js`. Product-level daily series `p.hist = { min, avg, byMerchant };`. Per-merchant series exist only for `os.slice(0, 3)`. `min` is the minimum offer *price* across merchants, with no shipping, coupon or country.
- **Source B:** `BACKEND-READINESS.md`: "Decide whether `price_snapshots` is per offer (correct, larger) or per product (cheaper, weaker)." The HTML methodology says "One snapshot per offer per day plus one on any material change." `PRICE-INTELLIGENCE.md` trend: "7/30/90-day moving averages of the cheapest total". `DATABASE.md`: `price_history` per `merchant_product`.
- **Chosen interpretation:** `offer_price_snapshots` is append-only per offer. It is written once per day and on every material change, holding price, old_price, availability, stock and currency. It is never updated; corrections are new rows. Product statistics (`avg7/30/90/365`, `low`, `low90`, `median`, `volatility`) come from a materialised `product_price_daily(product_id, market, date, min_total, avg_total, offer_count)`. That table is computed from **totals per delivery market**, because the published claim is about the cheapest total. The product-level `min` series is kept only as a derived aggregate.
- **Why:** T fixes per-offer append-only storage. The "cheapest total" basis follows the domain doc (5) and the methodology text in the code (1). The seed's price-only series is a generator shortcut.
- **Backend impact:** The table is partitioned by month and dominates write volume. `histStats`, `fakeDiscount`, `priceBadge`, `timing` and `forecast` read the aggregate or the per-offer series. Labels' "shop's own 90-day high" reads the per-offer series. Per-merchant fake-discount detection (AUDIT §C) stops using partial data. Stock history (BACKEND-READINESS §4.6) can use the same snapshots, because availability is on each row.

## C-05 · Compliance: missing-rule default and `unknown` semantics

- **Source A:** Code, `comp()` in the HTML. With no rule it returns `status: 'allowed', reason: 'No country-specific restriction on record.', source: 'Default policy'`. `buildProduct()` has `const blocked = c.status === 'not_allowed' || c.status === 'prescription_only';`, so `unknown` offers are listed. `intel.js` applies `pen('Market status not verified', 6)` and removes Best-value eligibility.
- **Source B:** `COMPLIANCE.md`: "not_allowed / prescription_only / unknown  →  offers = [], purchasable = false, reason returned". It also says "New products from feeds enter as `unknown`". `DATABASE.md`: offers are readable only when status "is `allowed` or `restricted`".
- **Chosen interpretation:** Decided in ADR-0007. A missing rule resolves to `unknown`, never `allowed`. Per status: `allowed` lists offers with purchase links and is recommendable. `restricted` lists offers with a warning and purchase links, and is not recommendable. `unknown` shows prices for information only, with no purchase links or CTA; it is excluded from Best value, every recommendation surface, alerts and sponsorship, keeps the −6 ranking penalty, and enters the admin review queue. `not_allowed` and `prescription_only` render an informational page with a reason, and no offers are serialized. For the demo import, `PrototypeSnapshotImporter` materialises the prototype's implicit default as explicit `allowed` rules with source "Default policy (prototype demo import)". Products created later from feeds have no rule, so they resolve to `unknown`. The policy is implemented in `App\Domain\Compliance\ComplianceStatus`, and changing it needs a new ADR, not a config switch. D-11 remains open for rule sources and ownership.
- **Why:** Target decision (T, compliance evaluated server-side) plus the product owner's specification for `unknown`: safe default, no purchase CTA, no recommendation, admin review queue. This deliberately deviates from the code (1) for `unknown` and for the missing-rule default. The deviation is listed in ADR-0010. Showing prices without CTAs keeps useful information that COMPLIANCE.md (5) would hide.
- **Backend impact:** A `ComplianceGate` runs before serialisation for offers, recommendations, alerts, sitemaps, programmatic pages, the redirect and sponsorship. Cache keys include the market. Absence of a row is treated as `unknown` in production code, never as `allowed`. There is a test matrix per status × surface.

## C-06 · Commercial signals reaching consumer surfaces

- **Source A:** `LABELS.md` refuses the "Official partner" label: "(a commercial relationship that shoppers read as a quality signal)". `PERSONALIZATION.md`: "Deals from shops you follow — falls back to highest-trust shops when you follow none." `PARTNER-NETWORK.md`: Partner tier = "Partner (Pro plan plus a measured delivery record)".
- **Source B:** Code. The shop page chip is `{{ sp.partner }}` → "Official partner", and offer-table rows show a "Partner" chip. `intel.js personal()` falls back to `S.merchants.filter((m) => m.partner)` under the title `'Deals from top-trust shops'`. `seed.js` has `sponsored: chance(0.08) && m.tier !== 'FREE'`. `offerRow()` carries `tier: m.tier`. The "Recommended" sort uses `row.score = rating * 8 - total * 0.35 + (m.verified ? 6 : 0) + …`, which is an unexplained second ordering.
- **Chosen interpretation:** No commercial property (plan, tier, partner status, sponsorship, commission, spend) may reach a consumer ordering, recommendation, label or trust presentation. Four changes follow. The For-you fallback uses the top Trust Score. The Partner and Official partner chips are removed from organic surfaces. A disclosed commercial relationship can appear only as the VISIBILITY "commercial marker" format. The "Recommended" sort is dropped or aliased to ComparoRank, because every ordering must be explainable. Offer DTOs for ranking carry no `tier`, `partner` or `sponsored` field.
- **Why:** T (ranking independence). LABELS (5) and PERSONALIZATION (5) also say so. The code instances are the "display next to the record" and commercial-leak defect class that AUDIT (3) tracks, so here the code is not the spec.
- **Backend impact:** `OfferContext`, `RecommendationContext` and `TrustContext` DTOs are closed classes. An architecture test fails if any of them gains a property from the Commercial, Billing, Affiliate or Visibility modules, as README and BACKEND-MIGRATION require. The recommendation service reads only trust and relationship data.

## C-07 · Sponsored / promoted rows vs organic order

- **Source A:** `COMPARORANK.md`: "Sponsored placements are labelled and ranked by the same score." `DATABASE.md` offer: "`is_sponsored`, `position_boost`, `active`." The HTML methodology says "sponsored offers are excluded from the calculation."
- **Source B:** `VISIBILITY.md`: "A promoted row is inserted, never re-sorted" … "position 1 is untouchable". The code's `visibility.js assemble()` splices promoted rows at declared slot indices and keeps `organicPosition`. `PROMOTION.md`: "Never for sale at any rung: a position in the offer table or ComparoRank".
- **Chosen interpretation:** The organic list is computed with no commercial input. Promoted rows are *inserted* by `assemble()` only on surfaces with declared slots: deal list, shop list, product list and search. The product offer table has no promoted slots. The seeded `offer.sponsored` flag becomes, at most, a disclosure that a campaign booking exists. It changes nothing in the table. `position_boost` is not created.
- **Why:** Code (1, `visibility.js`) and newest seed (2, `seed-visibility.js`) outrank COMPARORANK (5) and DATABASE (6). AUDIT §L (3) records the change: "Promotion now moves position, without touching the ranking."
- **Backend impact:** Offers have no `is_sponsored` or `position_boost` column. Surface assembly is a separate `PromotionAssembler` that runs after the ranking query. Each assembled row carries `organic_position` and `promoted` in the payload. Caps, holdback and render-time gate re-checks come from C-26.

## C-08 · ComparoRank port: formula, cache key, tie-break, fallbacks

- **Source A:** `BACKEND-MIGRATION.md` worked example. It has `'delivery' => $this->clamp(1 - ($ctx->deliveryDays - 1) / 6)` and `'rating' => $ctx->rating / 5`, with completeness as a weighted factor and only the stale penalty. Its table reads "`intel.js → comparoRank()` | on offer write; cached per (offer, country)".
- **Source B:** Code, `intel.js rank()`. It has `delivery: clamp(1 - ((ctx.deliveryDays || 6) - 2) / 8, 0, 1)`. Its review term is `((rating-3)/2) × damping(log10 reviewCount)` and its shipping term is `1 - ship/(shipMedian*2)`. Weights are renormalised to 100. It adds a separate "Offer quality" bonus of up to 7 and six penalties (stale −10, anomaly −14, reference price −8, compliance unknown −6, link −10 hidden, risk −6/−14 hidden). `ARCHITECTURE.md` caches "per (product, country, weights version)". `PARTNER-NETWORK.md` says "the tie-break is measured delivery, then review weight." The code tie-break is `a.totalNum - b.totalNum`.
- **Chosen interpretation:** Port `intel.js rank()` exactly, including its key names (`reviews`, not `rating`), the weights in `S.ix.rankWeights` {price 30, trust 20, delivery 14, reviews 12, freshness 10, availability 8, shipping 6}, the quality bonus, the penalties and the hidden-penalty count. There is no function called `comparoRank()`. Scores are cached per (product, market, weights_version). An offer score depends on `marketMin` and `shipMedian` across all offers of the product, so a per-offer cache is invalid. The tie-break is lowest total, as in the code. The measured-delivery tie-break is flagged as a doc-only proposal. The prototype's missing-input fallbacks (`trust 60`, `deliveryDays 6`, `rating 4`, `reviewCount 50`, `freshnessHours 12`, `completeness 0.8`) are ported as named constants, and the breakdown marks them "default — not measured".
- **Why:** Code (1) outranks COMPARORANK (5, which agrees with the code), ARCHITECTURE (7) and BACKEND-MIGRATION (8). PARTNER-NETWORK is a domain doc (5) below the code.
- **Backend impact:** `RankingService::score(OfferContext): RankResult` is pure, and golden-file tests against prototype outputs are the parity check. `marketStats()` in the code computes totals *without coupons* while the ranked total includes them. Port that as-is and flag it in D-10. Weights live in a versioned `ranking_weight_sets` table. The Ranking Lab publishes a new version with `ranking.publish` and an audit entry.

## C-09 · Two Trust Score formulas

- **Source A:** `intel.js trust()` is "Merchant Trust Score 2.0". It has 12 components (`['rating', 'Review score', 14]`, `['biz', 'Business verified', 14]`, …), a community-report penalty of up to 8, and the labels Highly trusted / Trusted / Generally reliable / Mixed signals / Low trust. It matches `TRUST-SCORING.md` and is used by offer rows, shop profiles and ComparoRank.
- **Source B:** HTML `trustScore(m)` has 7 components: "Review rating 25, Verified-review share 15, Merchant verification 15, Complaint resolution 15, Response 10, Price accuracy 10, Feed freshness 10". Its labels are Excellent / Good / Mixed / Weak. It is used by Ask Comparo answers, the shop-directory assembly, ingredient-page shop lists and the SEO wins table. The HTML methodology page says "A weighted composite: review rating 25 %, verified-review share 15 %, merchant verification 15 %, complaint resolution 15 %".
- **Chosen interpretation:** There is one Trust Score, `intel.js trust()` (Trust Score 2.0), with its labels. The HTML `trustScore()` is a legacy duplicate and every caller moves to TrustService. The methodology prose is generated from `trustDefs`.
- **Why:** Both are code (1), so the tie is broken by the domain doc (5, TRUST-SCORING) and by AUDIT (3) §I.4, which flags methodology prose that quotes weights in words.
- **Backend impact:** There is one `merchant_trust_scores` table with daily snapshots for `trustHistory`. Ask Comparo, directories and SEO tables read the same row, so two trust numbers for one shop can never exist. Add a test that the methodology page renders the weights from config.

## C-10 · Two price-badge / price-statistics implementations

- **Source A:** `intel.js priceBadge()`: "Exceptional price" within 2 % of the 90-day low, "Good price" ≥ 6 % below the 90-day average, then Typical / Above average. Volatility is (max−min)/mean over 90 days. `PRICE-INTELLIGENCE.md` agrees.
- **Source B:** HTML `priceStats()`, bound to `pdPrice` on the same product page. It uses badges "All-time low", "Near historical low" (≤ 3 % over), "Good price" (≤ −5 %), "Above average" (≥ +6 %) and "Average price". Volatility is a standard deviation over the mean. `cur` is the lowest `effNum` (after coupon, before shipping), yet its text says "today's lowest total".
- **Chosen interpretation:** `intel.js` `histStats` / `priceBadge` / `timing` / `forecast` is the only price-statistics definition. It runs on the per-market *total* series from C-04. The HTML `priceStats()` badge and volatility are retired. All-time low is kept as an extra fact, not as a second badge.
- **Why:** Code against code, resolved by the domain doc (5). It is also the AUDIT §7 rule that one statement has one source of truth.
- **Backend impact:** There is one `PricingService::stats(product, market)`, and product page, API, JSON-LD facts (GEO) and alerts all read it.

## C-11 · Market count: 12 "European" vs 27, US included

- **Source A:** `README.md`: "Independent price and trust comparison for sports nutrition across 12 European markets." `seed.js` lists 12 countries including `{ iso: 'US', name: 'United States', currency: 'USD', … }` and GB. `MARKET-EXPANSION.md` lists "United States — Early".
- **Source B:** `DELIVERY-MARKETS.md`: "Twelve markets became **27**." and "**27 delivery markets, 12 localised storefronts.**" `seed-geo.js` adds 15 markets. `seed-seo.js` `localeMarkets` holds only 10 locale–market pairs, and `S.locales` has 7 languages.
- **Chosen interpretation:** `countries` holds the 27 delivery markets from `seed-geo.js`, the newest source. "Storefront" is a separate flag and locale mapping (`market_storefronts`). The prototype has 10 storefront pairs, not 12, and 7 languages. US and GB are in the data as non-EU markets (`NON_EU = ['GB', 'US', 'CH', 'NO']`). Which markets and storefronts launch is open decision D-01.
- **Why:** Newest seed (2) and AUDIT §J (3) outrank README. "12 localised storefronts" does not match the code's 10 pairs, and the code (1) wins on the actual list.
- **Backend impact:** `countries(iso2, currency, vat_rate, min_age, eu_member, region, customs, active_delivery, storefront_enabled)`. Per-market feature flags come from MARKET-INTELLIGENCE. Shipping lanes use `shipping_zones(merchant_id, country, carrier, cost_minor, days_min, days_max, cutoff, …)` with the 155 lanes. Compliance baselines are needed for all 27 markets.

## C-12 · Orders: "we process no orders" vs a full order record

- **Source A:** `README.md`: "Comparo never sells products, holds stock or processes orders." It also says "Shops do not give us order data, so verification rests on evidence we can check ourselves".
- **Source B:** `ORDERS.md` record includes "placedAt, shippedAt, deliveredAt, promisedDays, actualDays," and "carrier, tracking,". `seed-orders.js` builds orders from verified reviews plus unreviewed purchases. `API-ENDPOINTS.md` has `POST /orders/{id}/return` and `/dispute`. DELIVERY-GUARANTEE and GOVERNANCE (returns index) are computed from these rows.
- **Chosen interpretation:** An `orders` row is a **purchase record held as evidence**, not an order Comparo processes. Its `source` is one of `affiliate_conversion`, `forwarded_confirmation`, `receipt`, `user_declared` or `merchant_confirmed`. Delivery, return and dispute facts are **events** from the shopper, such as a delivery report (CONTRIBUTIONS) or a claim, or from a network or merchant where available. They are never fabricated. Delivery stats and the returns index keep the 8-order minimum. The prototype's generated dispatch and transit timings are seed data only. They are not a model of what production receives.
- **Why:** Newest seed (2) and ORDERS (5) define the entity, and AUDIT (3) closes the gap. README's sentence is about business model, not schema. The two are compatible once the data source is explicit.
- **Backend impact:** `orders` plus `order_events(type: shipped|delivered|returned|disputed|claim, source, reported_by, at)`. Order lines are needed, because ORDERS says "A real basket order has lines". The real data supply for measured delivery is open decision D-15.

## C-13 · Purchase-verification vocabulary and proof keying

- **Source A:** `README.md` lists three routes: "Click match", "Forwarded confirmation", "Uploaded receipt". The code's `purchaseProofRoutes()` has keys `click`/`email`/`receipt`, with the proof keyed `const key = kind + ':' + targetId;`.
- **Source B:** `REVIEWS.md` lists four methods, including "| Merchant confirms the order | Merchant-confirmed |" and "| Manual check by our team | Admin-confirmed |". `seed-orders.js` has `verifyMethod = o.source === 'comparo_click' ? 'affiliate_conversion' : 'order_reference'`. `API-ENDPOINTS.md` has `/verification/click-match|email|receipt`.
- **Chosen interpretation:** Two separate enums. `purchase_proofs.route` is `click|email|receipt` and describes how evidence arrived. Its status is `pending|verified|rejected`. `reviews.verify_method` is `affiliate_conversion|order_reference|merchant_confirmed|admin_confirmed` and describes what the evidence proved. It is derived from the proof or order, never set by hand. Proofs are keyed per (user, review, order), because keying per target is a single-user prototype artefact.
- **Why:** Code (1) plus the newest seed (2) supply both vocabularies, and REVIEWS (5) adds the two manual methods that the seed already emits (`merc…`).
- **Backend impact:** `purchase_proofs(user_id, review_id, order_id, route, status, evidence jsonb, decided_by, decided_at)` and a moderation queue endpoint. The label text is derived: "Verified — click matched · ORD-…". The click-match check queries `affiliate_clicks` for this user and merchant within 45 days.

## C-14 · Rating aggregation

- **Source A:** `DATABASE.md`: "Aggregate ratings are always computed from approved reviews — never stored by hand, never seeded." The HTML methodology says "Ratings are the arithmetic mean of published reviews". `BACKEND-READINESS.md`: "`ratingOf()` averages all approved reviews equally."
- **Source B:** Code, `reviewWeight()`. `AUDIT.md` B3: "verified purchase 1.0, normal 0.75, needs review 0.6, suspicious 0.25." Shop ratings blend the seeded population figure: `const blended = m.rating * (1 - share) + heldAvg * share;`.
- **Chosen interpretation:** The average is credibility-weighted with the code's weights, over **held approved reviews only**. The seeded population blend (`m.rating` × `m.reviews`) is not ported. AUDIT B3 itself says a real deployment "holds every record and the blend collapses". `reviews.weight` is denormalised by one writer job with a parity test (AUDIT §7).
- **Why:** Code (1) and AUDIT (3) set the weighting. DATABASE (6) sets "never seeded". Together they give a weighted average with no seeded aggregates.
- **Backend impact:** The rating SQL uses `sum(rating*weight)/sum(weight)`. `reviews.weight` is recomputed on the `ReviewTrustChanged` and `PurchaseProofDecided` events. `AggregateRating` JSON-LD is emitted only when approved reviews exist. The shop "Reviews 3,421" population figure has no production counterpart unless D-19 imports one.

## C-15 · Trust inputs: silent defaults, seeded vs measured delivery

- **Source A:** `README.md`: "Where data is incomplete, the interface says so." `ORDERS.md`: "Delivery reliability was a seeded percentage." and "Returns do not move merchant trust."
- **Source B:** Code, `intel.js trust()`. It has silent defaults such as `priceAcc: clamp(((a.priceAccuracy || 92) - 70) / 30, 0, 1)`, and the public "Shipping reliability" reads `r1(a.deliveryOnTime || 85) + ' % on time'` from seeded `m.ix`. Meanwhile `deliveryStats()` measures on-time from orders.
- **Chosen interpretation:** Keep the Trust 2.0 weights and formula. Each sub-signal is sourced from a measured table: shipping accuracy and on-time from `deliveryStats` when the sample is ≥ 8, price accuracy from landing-page sampling, feed uptime from `feed_runs`, and so on. Where no measurement exists, the code's fallback value is used and the breakdown row is marked "not measured". Returns are *not* added to trust until decided, as ORDERS says.
- **Why:** Code (1) defines the formula. README's principle is honoured by labelling, not by changing weights, which keeps parity.
- **Backend impact:** `merchant_signal_values(merchant_id, signal, value, measured bool, sample, as_of)`. TrustService records `measured` per component. A public signal with `measured=false` renders as an estimate.

## C-16 · Plan and tier vocabularies

- **Source A:** `DATABASE.md` merchant `tier` (`free|pro|premium`). `seed.js`: `price: m.tier === 'PREMIUM' ? 399 : m.tier === 'PRO' ? 149 : 0`.
- **Source B:** `seed-commercial.js` plans FREE / PRO / GROWTH (399) / ENTERPRISE with versioned entitlements. `PAID-ADDONS.md`: "Comparo Plus €3.90 / Pro €8.90" for buyers. `MERCHANT-SAAS.md` account status "(Free, Trial, Paid, Enterprise, Paused, Past Due, Cancelled)" vs `SUBSCRIPTIONS.md` "Statuses: Trialing, Active, Past Due, Paused, Cancelled, Expired."
- **Chosen interpretation:** Merchant plans are `FREE|PRO|GROWTH|ENTERPRISE` from `seed-commercial.js`, and the importer maps `PREMIUM → GROWTH`. `merchants.tier` is not a column; the plan is derived from the active subscription. Buyer tiers get their own namespace (`buyer_free|buyer_plus|buyer_pro`) so "Pro" never collides. Subscription status uses the SUBSCRIPTIONS enum. The commercial *account* status in MERCHANT-SAAS is a derived view (for example Trial = trialing subscription).
- **Why:** Newer commercial seed (2, which loads after `seed.js`) outranks `seed.js` legacy fields and DATABASE (6).
- **Backend impact:** `plans`, `plan_versions`, `feature_entitlements`, `subscriptions(plan_version_id)`. Entitlement checks go through one `EntitlementService`. Nothing gates on a plan name string.

## C-17 · Enum / status naming

- **Source A:** `DATABASE.md` coupon "`discount_type` (`percent|fixed|free_shipping|bundle`)" and merchant "`status` (`pending|verified|rejected|suspended`)".
- **Source B:** `seed.js`: `pick(['percent', 'fixed', 'freeship'])`. `VISIBILITY.md` shop profile: "Status machine: draft → submitted → in review → changes requested → approved → published, plus suspended". `INVOICING.md`: "Statuses: Draft, Open, Paid, Past Due, Void, Credited." (Title Case). ORDERS: "placed | in_transit | delivered | returned | disputed".
- **Chosen interpretation:** All enums are PHP backed enums with snake_case values, taken from the code's values. `coupon.type` is `percent|fixed|free_shipping`, mapped from `freeship`. `bundle` is dropped because nothing generates or prices it. Merchant *verification* status and shop *profile application* status are separate machines. Invoice and subscription values are snake_case (`past_due`, `void`). The coupon state enum is `verified|merchant|community|unverified|expired|invalid`. Review status is `pending|approved|rejected|flagged|hidden`.
- **Why:** Code (1) values win, and DATABASE (6) naming is used only where the code has no value.
- **Backend impact:** Enum classes are shared by migrations (Postgres check constraints or native enums), Inertia props and TypeScript types generated from PHP enums.

## C-18 · Endpoints mapped to prototype functions that do not exist

- **Source A:** `API-ENDPOINTS.md`: "Every endpoint below corresponds to a function that already exists in the prototype." `BACKEND-MIGRATION.md` and `BACKEND-READINESS.md` name engine functions.
- **Source B:** A grep of the HTML and engine files shows these names do not exist, and gives the real ones:

| Name in docs | Doc | Actual code |
|---|---|---|
| `comparoRank()` | BACKEND-MIGRATION | `ix.rank(ctx)`; context built in `enrichRow()` |
| `matchScore()` | BACKEND-MIGRATION | `ix.match(item)`; HTML `matchItem()` is a demo paste matcher |
| `riskScore()` | BACKEND-MIGRATION, READINESS | `ix.risk(m)` |
| `priceStats()` / `anomaly()` (intel) | BACKEND-MIGRATION | `ix.histStats()`, `ix.anomalies()`; HTML `priceStats()` is the legacy one (C-10) |
| `buildBrand()` | API-ENDPOINTS | `buildBrands()` (index and detail) |
| `buildIngredients()` | API-ENDPOINTS | `buildIngredient(slug)` |
| `buildCountry()` | API-ENDPOINTS | `buildCountryHub(iso)`, `buildMarkets()` |
| `reportCoupon()` | API-ENDPOINTS | `voteCoupon(code, ok)` |
| `suggestions()` | API-ENDPOINTS | none found |
| `ix.searchIntent()` | API-ENDPOINTS | intent classification inside `parseNL()` |
| `voteHelpful()` | API-ENDPOINTS | `vote(id, 'up'\|'down')` |
| `buildThread()`, `addReply()`, `voteThread()` | API-ENDPOINTS | `buildForum()` topic branch + `threadRow()`, `newReply()`, `voteUp()` |
| `buildUserProfile()` | API-ENDPOINTS | `buildProfile(username)` |
| `notifications()` | API-ENDPOINTS | `notifRows()` |
| `feedHealth()` | API-ENDPOINTS | none found (feed data comes from `S.feeds` inside `buildMerchant()`) |
| `ix.matchCandidates()` | API-ENDPOINTS | `ix.matchBuckets()`, `ix.clusters()` |
| `ix.benchmark()` | API-ENDPOINTS | `ix.benchmarks()`, `ix.competitiveness()` |
| `cm.reconciliation()` | API-ENDPOINTS | seed data `cx.reconciliation` read by `cm.affiliateHealth()` |

- **Chosen interpretation:** The right-hand column is the source to port. The endpoints still exist, re-pointed to the real functions. `suggestions` and `feedHealth` have no prototype logic and are specified fresh: typeahead is a Meilisearch prefix query, and feed health is an aggregate over `feed_runs`.
- **Why:** Code (1) outranks the endpoint map (6) and BACKEND-MIGRATION (8).
- **Backend impact:** The API-ENDPOINTS "Prototype source" column is corrected in the port backlog, so parity tests point at real functions.

## C-19 · Route naming

- **Source A:** `SEO.md`: "/countries/{country}  market hub". The code canonical is `'/countries/' + slug(country name)`, for example `/countries/germany`. `COMMUNITY.md`: "country hubs (`/country/{iso}`)". The code also links `'#/country/' + iso`.
- **Source B:** `API-ENDPOINTS.md`: "`/markets` / `/markets/{iso}` | `buildCountry()`". `API.md`: "GET  /api/v1/countries/{iso}/hub". `DELIVERY-MARKETS.md`: "Surface: `#/markets`". There are other drifts too: `/me/saved` (API-ENDPOINTS) vs `/me/favorites` (API.md), `/threads` vs `/forum/threads`, and `/shops` (web) vs `/merchants` (public API).
- **Chosen interpretation:** Web routes mirror the prototype hash routes without `#`. `/countries/{name-slug}` is the canonical market hub. `/country/{iso}` 301s to it. `/markets` is the index of the 27 delivery markets. The internal API uses `/markets/{iso}`. `/forum`, `/forum/{category}` and `/forum/topic/{slug}` are canonical web routes. Account saves use `/me/saved`, the name the code uses (`toggleSaved`). The monetised public API keeps `merchants` as its resource noun and documents the alias.
- **Why:** Code (1) plus SEO (5) outrank API docs (6).
- **Backend impact:** Named Laravel routes, a redirect map for aliases, and a sitemap generator that uses canonical names only.

## C-20 · Permissions and roles

- **Source A:** `API-ENDPOINTS.md` permissions `catalog.merge`, `pricing.review`, `growth.view`, `commercial.view`, `automation.manage`, `audit.view`. `DATABASE.md` user `role` (`user|editor|moderator|affiliate_manager|compliance_manager|account_manager|admin|superadmin`).
- **Source B:** Code. `seed-intel.js` perms: `'matching.resolve'`, `'automation.edit'`, `'audit.export'`, `'risk.act'`, `'ranking.simulate'`, `'ranking.publish'`, `'user.pii'`. `seed-commercial.js` has `cx.permissions`. The two role lists overlap: "Affiliate Manager" is `affiliate` in one and `affiliatemgr` in the other, and "Analyst" exists in both with different permissions.
- **Chosen interpretation:** spatie/laravel-permission uses the code's permission names. The API-ENDPOINTS names map as `catalog.merge → matching.resolve`, `automation.manage → automation.edit` and `audit.view → audit.export`. `pricing.review`, `growth.view` and `commercial.view` are not in code and are added explicitly as *new* permissions. They are flagged, not assumed. Roles are the union of `ix.roles` and `cx.roles`. The two duplicate names merge into one role each. Each merged role starts with the intersection of the two permission sets, until the owner approves the union (D-29). DATABASE's single `role` column is replaced by spatie roles.
- **Why:** Code (1) outranks the docs (6).
- **Backend impact:** A roles-and-permissions seeder generated from both seed lists. Every privileged action is audit-logged. Growth and Commercial consoles need the new view permissions, and the owner must confirm them.

## C-21 · API conventions (errors, pagination, money shape)

- **Source A:** `API.md`: "Cursor pagination (`?cursor=&limit=`)." and errors `{ "error": { "code": "validation_failed", … } }`. Money is a decimal.
- **Source B:** `API-ENDPOINTS.md`: "Errors: RFC 7807 problem+json." with `page` and `per_page`. Money is in minor units.
- **Chosen interpretation:** RFC 7807 errors. Page-based pagination for catalogue indexes, and cursor pagination for append-heavy feeds (activity, clicks, audit, notifications). Money follows C-02. Lists carry `meta.country`, `meta.currency` and `meta.generated_at` (API.md), plus `meta.excluded` (API-ENDPOINTS).
- **Why:** API-ENDPOINTS is the implementation map. Both are level 6, API-ENDPOINTS is the newer and more specific one, and T settles money.
- **Backend impact:** Shared exception renderer and pagination macro. This applies only to `/api/v1`. Inertia pages use Inertia props and validation errors.

## C-22 · Mandatory country vs crawlable default page

- **Source A:** `API-ENDPOINTS.md`: "return `422` rather than defaulting silently." This applies when no delivery country is given.
- **Source B:** `INDEXING.md`: "The default canonical experience is crawlable; a user's delivery-country preference changes presentation only." `COMPLIANCE.md`: "country resolved (user selection → account default → IP country hint → site locale)".
- **Chosen interpretation:** `/api/v1` price endpoints stay strict and return 422 without `country`. Web pages resolve the country with the COMPLIANCE cascade and end at a configured default market for crawlers and anonymous visitors (D-24). The rendered page states the market it priced for. Market-specific canonical content lives only on `/countries/{…}`.
- **Why:** Both apply to different surfaces. COMPLIANCE (5) and INDEXING (5) govern web, and API-ENDPOINTS (6) governs the API.
- **Backend impact:** A `ResolveMarket` middleware for web and a validation rule for the API. Cache keys include market and currency.

## C-23 · Matching-engine scales and bands

- **Source A:** `MATCHING-ENGINE.md` and `intel.js match()`: 0–100 points (EAN +50 …), `const bucket = score >= 90 ? 'auto' : score >= 65 ? 'confirm' : 'unmatched';`.
- **Source B:** `MERCHANT-FEEDS.md`: "≥ 0.90 → `auto`; 0.60–0.90 → `suggested` (human confirms); < 0.60 → `unmatched`." It adds a "previous decision" cascade step. `KNOWLEDGE-GRAPH.md`: "**99 % exact / 92 % high / 78 % possible / unknown**". The HTML `matchItem()` returns EAN → `confidence: 0.98` and fuzzy `bs >= 34`.
- **Chosen interpretation:** The `intel.js match()` point model and bands are canonical: 100 exact, ≥ 90 auto, 65–89 confirm, < 65 unmatched, with the explainable breakdown. Match type values follow DATABASE (`auto|suggested|manual|created|unmatched`) with `confirm → suggested`. Reusing a previous decision for the same (merchant, SKU) is documented in two docs but absent from the code. It is included as step 0 and flagged here as a doc-only addition. HTML `matchItem()` is a demo helper and is not ported.
- **Why:** Code (1, the engine with the breakdown the UI renders) outranks domain docs (5).
- **Backend impact:** `match_confidence` is a smallint 0–100. `MatchingService` runs in a queue per feed run and implements `variantGuard` on merges. Compliance status is never inferred.

## C-24 · Price-anomaly thresholds and flag source

- **Source A:** `PRICE-INTELLIGENCE.md` and `intel.js anomalies()`: "Zero price, > 55 % below the product median, > 120 % above it."
- **Source B:** `AUTOMATIONS.md`: "| Price anomaly hold | ±60 % vs median |". `DATA-QUALITY.md`: "Prototype alerts for price drops beyond 70 %". Ranking and `publicRows()` read the *seeded* `o.ix.anomaly`, not the detector's output.
- **Chosen interpretation:** One detector uses the `intel.js` thresholds (0, < 45 % of median, > 220 % of median) plus import rejections. Its output is the only writer of `offers.anomaly_state`. Ranking, `publicRows`, Best value and aggregates read that state. Admin decisions (genuine, exclude, contact) are recorded.
- **Why:** Code (1) plus the matching domain doc (5). The other numbers are prose.
- **Backend impact:** `price_anomalies` table, a `PriceAnomalyDetected` event, and `ranking.invalidate`. The automation rule's parameter reads the detector config.

## C-25 · Freshness / stale thresholds

- **Source A:** `DATA-QUALITY.md`: "| Stale | 1–7 days |", "| Expired | > 7 days, or availability unknown |".
- **Source B:** Code. `stockConfidence` is `ageH <= 6 ? 'High' : ageH <= 24 ? 'Good' : ageH <= 48 ? 'Moderate' : 'Unknown'`. `priceConfidence` treats fresh as < 24 h. `rank()` gives stale > 48 h −10, and the offer row has `stale = … > 48 * 3600000`. `COMPARORANK.md` also says > 48 h.
- **Chosen interpretation:** Use the code thresholds: 6 / 24 / 48 h for stock confidence, 24 h freshness for price confidence, and the 48 h stale penalty. After 48 h an in-stock claim shows "Unknown". Automatic expiry (hiding) is not in code. It is open decision D-25.
- **Why:** Code (1) outranks DATA-QUALITY (5).
- **Backend impact:** Thresholds live in `config/pricing.php`. A scheduled job marks staleness and invalidates the rank cache when a threshold is crossed.

## C-26 · Three placement inventories, two pricing models, two cap sets

- **Source A:** `SPONSORED-PLACEMENTS.md`: "Eight placements with fixed capacity per market". It prices by base price and model (fixed / CPM / CPC / CPA) and caps at "3 sponsored units per session, 8 per day." `seed-addons.js` extends `cx.placements` to 13 ("13 placements, 8 formats" in PAID-ADDONS). `CAMPAIGN-MARKETPLACE.md` has six plan-gated products.
- **Source B:** `seed-visibility.js` / `visibility.js` has 12 surfaces with slot indices, caps, gates and a 20 % holdback. The price is "Surface base × market weight … × scarcity (capped at +40 %". Caps are at most 2 promoted rows per list, never two in the first screen and ≥ 70 % of the fold organic.
- **Chosen interpretation:** There is **one** inventory model, `ad_surfaces`, seeded from `vs.surfaces`. Each `cx.placements` row maps to a surface plus a format (home, newsletter and research become non-list formats). Enforcement comes from `visibility.js`: slots, holdback, per-list caps, gates re-checked at render, and refund when a gate fails. The pricing model per surface is the visibility calculation. The commercial models (CPM/CPC) are kept as the *billing basis* recorded on the campaign. Session and day frequency caps from SPONSORED-PLACEMENTS apply on top. How the two catalogues are finally merged commercially is open decision D-20.
- **Why:** Both are code and seed (1, 2). The visibility engine is the one wired into list assembly and gating, and AUDIT §L (3) makes it the governing model. The commercial seeds provide billing records.
- **Backend impact:** `ad_surfaces`, `surface_slots`, `inventory(surface, market, month)`, `bookings`, `campaigns`, `campaign_deliveries`. The compliance gate blocks restricted and unknown products (SPONSORED-PLACEMENTS rules 3–4).

## C-27 · Public commercial pages vs commercial gate

- **Source A:** `COMMERCIAL-TRANSPARENCY.md`: "**Not yet built** (planned public surfaces, deliberately not claimed as existing):". `BACKEND-READINESS.md` lists `/for-merchants/pricing`, `/developers` and `/advertising` as missing.
- **Source B:** `AUDIT.md` B10: "**CLOSED.** All three built in `buildPublicCommercial(kind)`." `VISIBILITY.md`: "Everything commercial is behind an approved shop profile". The code has `V.isCommercialGate = (commercialRoute || r.name === 'visibility') && !sp0.published;` for advertising, promote, partners, developers, pricing and add-ons.
- **Chosen interpretation:** The routes exist. Without a published shop profile they render `buildCommercialGate()`, which shows every rule, cap, the never-for-sale list and the monthly sponsored click share, but no prices. With a published profile they render `buildPublicCommercial()`. Whether `/developers` (data customers, not shops) should sit behind a *shop* profile is open decision D-21.
- **Why:** Code (1) and AUDIT §L (3), which comes after B10, outrank the older docs.
- **Backend impact:** `EnsureShopProfilePublished` middleware on commercial routes. Gate content is rendered from the same config as the priced page, so the rules cannot diverge.

## C-28 · API volume: entitlement quota vs API plan; rate limits

- **Source A:** `PLANS-ENTITLEMENTS.md`: "`api.requests_month` (0 / 10k / 100k / 1M)". `API-ENDPOINTS.md`: "| `GET /public/v1/products` | Developer+ | 60/min |", with 120/min and 300/min for higher plans. `API.md`: "merchant API 600/min".
- **Source B:** `seed-commercial.js` has the comment "the API entitlement is a gate, never a quota — volume is sold by the API plan alone". `API-MONETIZATION.md`: "Developer (free, 5k requests, 5 req/s, community support)". Business is 250k and 25 req/s with €4 per 1k overage.
- **Chosen interpretation:** The merchant plan entitlement only *gates* API access. Volume and rate come from `api_plans`: DEV 5k/month at 5 req/s, BIZ 250k/month at 25 req/s with overage, ENT custom. The per-minute figures in API-ENDPOINTS are superseded.
- **Why:** Code and seed (1, 2) outrank the domain doc (5) and API docs (6).
- **Backend impact:** Laravel rate limiters keyed by API key and plan. Usage metering goes to `api_usage` (daily rollups). The upsell trigger fires at 85 % of plan.

## C-29 · Affiliate redirect: interstitial, latency, compliance, commission display

- **Source A:** `API-ENDPOINTS.md`: "4. `302` — never render an interstitial that delays the user." with a "< 50 ms" target. `ARCHITECTURE.md` and `AFFILIATE.md` set p99 < 40 ms. `AFFILIATE-SALES.md`: "Commission terms are not published unless deliberately disclosed".
- **Source B:** `AFFILIATE.md`: "the interstitial at `/go/...` repeats it with the commission rate and cookie window before the user proceeds". Code: `buildGo()` renders an interstitial with a 3-second countdown (`goCount: 3`) that shows `commission: m.affiliate.commission.toFixed(1) + ' %'`. It does *not* re-check compliance, although AFFILIATE step 2 requires it.
- **Chosen interpretation:** Keep the disclosure interstitial from the code. `/go/{merchant}/{product}` renders it with an immediate "Continue" link and auto-continue. The actual 302 hop (`/go/{merchant}/{product}/out`) carries the latency target, with the click written to a Redis buffer. Compliance and merchant suspension are re-checked server-side before either hop, because AFFILIATE and T require it. Showing the merchant-specific commission rate is kept behind a flag, pending network terms (D-17).
- **Why:** Code (1) plus AFFILIATE (5) outrank API-ENDPOINTS (6) on the interstitial. T (server-side compliance) adds the missing check.
- **Backend impact:** Two routes. `FlushClickBuffer` runs every 10 s. `affiliate_clicks` is partitioned monthly. Both routes are `noindex`/`nofollow` and robots-disallowed.

## C-30 · Conversion webhook path and network list

- **Source A:** `API.md` and `AFFILIATE.md`: "POST /api/v1/webhooks/networks/{network}   (HMAC-signed conversion postback)".
- **Source B:** `API-ENDPOINTS.md`: "`POST /webhooks/affiliate/{network}` receives conversion postbacks (Awin, Tradedoubler, Impact, direct)." `PARTNER-NETWORK.md`: "Six networks — Direct, Awin, Tradedoubler, Impact, Daisycon, Partnerize".
- **Chosen interpretation:** `POST /api/v1/webhooks/affiliate/{network}`, HMAC-signed, with a 5-minute replay window and idempotency on (network, network_order_id). The network registry holds the six networks from `seed-network.js` with cookie window, validation delay, dedup policy and payment terms. Which adapters are built first is open decision D-03.
- **Why:** Newest seed (2) supplies the network list. For the path, API-ENDPOINTS is the implementation map (6). AFFILIATE's security rules (5) are kept.
- **Backend impact:** An `AffiliateNetworkAdapter` interface, a nightly report pull per network that overwrites `pending` rows only, and reconciliation (`cx.reconciliation`) based on sub-id.

## C-31 · Three points currencies (reputation, XP, Comparo points)

- **Source A:** `REPUTATION.md` has five levels ending "| Top Contributor | 1500 |". `REFERRALS.md`: "5 → 250 Comparo points". The HTML keeps `state.reputation` and `rep()`.
- **Source B:** `CONTRIBUTIONS.md`: "Ten levels, each unlocking capability rather than decoration". `seed-gamify.js` overrides `S.levels` with 10 XP levels. `AUDIT.md` §M: "a parallel points system would be a second truth about the same member." `governance.js` draws the jury pool with `(u.rep || 0) >= 900` but checks eligibility with `(m.xp || 0) >= 900`.
- **Chosen interpretation:** There is one append-only contribution ledger (XP) with the 10 levels from `seed-gamify.js`, the 48 h provisional rule and daily caps. Reputation and Comparo points are views or aliases of that ledger, not separate balances. The jury pool and eligibility both read XP. DATABASE's `reputation_event` table *is* that ledger, with reversals written as negative rows.
- **Why:** Newest seed (2) and AUDIT §M (3) outrank REPUTATION and REFERRALS (5). The `rep` vs `xp` split in `governance.js` is a code defect under the same AUDIT rule.
- **Backend impact:** `contribution_ledger(user_id, source, xp, status provisional|confirmed|reversed, entity, at)` and a cached level on the profile. Rewards map to entitlements: 1,200 XP = 30 days of Plus, if buyer tiers ship.

## C-32 · Jury panel size

- **Source A:** `GOVERNANCE.md`: "Nine seats, five votes decide, 72 hours".
- **Source B:** `AUDIT.md` §M says seats come from the drawn panel. `governance.js` clamps the draw to the eligible pool and uses `const quorum = Math.floor(s / 2) + 1;`. `seed-governance.js` has "Up to nine members are drawn and a majority of the seated panel decides."
- **Chosen interpretation:** Up to nine seats. The quorum is a majority of the seated panel, and the draw is deterministic from the case id. An open case keeps one seat free for the voter.
- **Why:** Code (1) and newest seed (2) outrank GOVERNANCE (5).
- **Backend impact:** `jury_cases`, `jury_seats` (drawn at open time and stored for audit, reproducible from `case_id`) and `jury_votes(reason)`. Whether juries are binding is part of D-18.

## C-33 · Locale strategy

- **Source A:** `INTERNATIONAL-SEO.md`: "Production URL shape: `/{locale}{entity-path}`", with 7 languages and a full hreflang matrix. `DELIVERY-MARKETS.md` claims "12 localised storefronts".
- **Source B:** `AUDIT.md` B9: "**Language switcher changes nothing.**" `BACKEND-READINESS.md`: "Decide whether translation is UI-only (Laravel lang files) or extends to content". `seed-seo.js` has 10 locale–market pairs, and most locales are marked `machine draft` or `missing`.
- **Chosen interpretation:** Unresolved in the repo, so it goes to D-02. Until then, the URL scheme reserves `/{locale}` prefixes, the locale defaults to `en`, hreflang is emitted **only for locales marked enabled**, and product data stays in its source language with a note.
- **Why:** AUDIT (3) and READINESS (4) state that the prototype has not decided. INTERNATIONAL-SEO (5) describes a target, not a running feature.
- **Backend impact:** `languages(enabled)`, `market_storefronts`, Laravel lang files, and an hreflang generator driven by enabled rows.

## C-34 · Retention of proof documents

- **Source A:** `API.md`: "Order confirmations are hashed for verification and discarded — no order documents are stored." `DATABASE.md`: `proof_hash` "never the document".
- **Source B:** `BACKEND-MIGRATION.md`: "// evidence: {click_id} | {sender_domain, order_date, total} | {file_path}". Code, receipt route: "A moderator sees it once and it is deleted after the decision."
- **Chosen interpretation:** Receipts are stored temporarily in private object storage. They are deleted by a job immediately after the moderation decision, or after a maximum retention (D-09) if undecided. What is kept is the hash plus the three extracted fields (merchant/sender domain, order date, total). Forwarded emails are parsed in memory and never stored.
- **Why:** Code (1) defines the receipt life cycle. API (6) and DATABASE (6) define what may remain.
- **Backend impact:** `purchase_proofs.evidence` has no body. A `PurgeDecidedProofDocuments` job and a storage lifecycle rule are needed.

## C-35 · Append-only audit log vs GDPR erasure

- **Source A:** `DATABASE.md` audit_log has `actor_id` and `actor_email` and is "Append-only; no deletes, no updates." `MODERATION.md` says there are no edits "including for administrators".
- **Source B:** `BACKEND-MIGRATION.md`: "Erase anonymises retained community content rather than deleting threads other people replied to." API `DELETE /me` is a GDPR erase.
- **Chosen interpretation:** The audit log stays immutable but holds no direct identifiers. It stores `actor_id` (a pseudonymous user id) and `ip_hash`, and no email. Erasing a user deletes or anonymises the identity record, so the log stays intact without identifying anyone. Retention is D-09.
- **Why:** Both are level 6/8 and compatible once the identifiers are pseudonymised.
- **Backend impact:** Remove `actor_email`. Postgres `REVOKE UPDATE, DELETE` on `audit_log`. An erasure job and export coverage per BACKEND-MIGRATION.

## C-36 · Device fingerprints

- **Source A:** `AFFILIATE.md`: "a rotating session hash, never a raw IP or a device fingerprint kept beyond attribution."
- **Source B:** `FRAUD-DETECTION.md`: "shared device fingerprint −16". `REFERRALS.md`: "Self-referrals (shared device fingerprint)". `DATABASE.md` has review `ip_hash` and `device_hash`. Prototype fingerprints are seeded (PROTOTYPE-LIMITATIONS).
- **Chosen interpretation:** Only salted, rotating hashes (IP truncated before hashing, and UA class) are stored, with a bounded retention (D-09). They are used for fraud and referral abuse only. No client-side fingerprinting script is used unless the DPO approves it (D-23).
- **Why:** The domain docs (5) conflict. The more privacy-preserving reading is chosen because it is reversible and ePrivacy-safe.
- **Backend impact:** A `RequestFingerprint` value object and a hash-salt rotation schedule. The −16 review-trust penalty uses the same hash.

## C-37 · Dosing schema

- **Source A:** `DATABASE.md`: `product_ingredient` — "`amount`, `unit`". `BACKEND-MIGRATION.md`: "IngredientProduct product_id, ingredient_id, amount_mg, per_serving".
- **Source B:** `DOSING.md`: "`product_ingredient` gains `amount_mg`, `is_carrier`, `nrv_mg`, plus `source` and `read_at` on the product." `seed-dose.js` holds mg per serving. Market limits carry `scope: 'daily'` and mg units (for example Vitamin D3 `max: 0.1`).
- **Chosen interpretation:** Follow DOSING and the seed: `amount_mg` (decimal), `is_carrier`, `nrv_mg`, plus `dose_source` and `dose_read_at` on the product. Market limits go in `ingredient_market_limits(ingredient, market, max_mg, scope serving|daily, rule)`. Carriers are excluded from active totals.
- **Why:** Newest seed (2) plus DOSING (5) outrank DATABASE (6) and BACKEND-MIGRATION (8).
- **Backend impact:** `cheapestSourceOf` runs as a nightly precompute into `ingredient_price_rank(ingredient, market, …)`. The wiki dosing edits (Authority level) write the same table as proposals.

## C-38 · Price alerts: shape and evaluation

- **Source A:** `DATABASE.md` `price_alert` has `target_total`, `currency`, `country_id`, `channels` and `status`. `API-ENDPOINTS.md`: "`addAlert()` — body `{product_id,target_price,type}`".
- **Source B:** Code `addAlert()` stores `{productId, target, types: [low, near, drop, best, coupon, ship, trust], priority, digest}` with no country. It evaluates only once, at creation. `AUDIT.md` B8: "**Alerts never fire.**"
- **Chosen interpretation:** `price_alerts(user_id, product_id, market, currency, target_total_minor, trigger_types[], channels[], priority, digest, status active|triggered|paused)`. An `EvaluatePriceAlerts` job runs on `PriceChanged` and nightly, and writes `alert_triggers` and notifications. It respects compliance (`restricted` and `unknown` products never alert).
- **Why:** Code (1) defines the trigger types. DATABASE (6) adds the market, which the code lacks and which totals need. AUDIT (3) marks evaluation as missing.
- **Backend impact:** New job and table. The "3 price alerts triggered" figures become derived.

## C-39 · Price-confidence scale

- **Source A:** `README.md`: "| **Price confidence** | 0–1 |".
- **Source B:** `PRICE-INTELLIGENCE.md`: "## Price confidence (0–100)". `intel.js priceConfidence()` returns 0–100, and `confidenceOf()` adjusts it by shopper reports (+12 / −34 caps, half weight after 14 days).
- **Chosen interpretation:** 0–100 integer, with report adjustment as in `confidenceOf()`.
- **Why:** Code (1).
- **Backend impact:** `smallint` column. The report weighting job is the single writer.

## C-40 · Two "unverified reference price" definitions

- **Source A:** `FRAUD-DETECTION.md` and `intel.js fakeDiscount()`: flagged when the "was" price is "≥ 25 % above the 12-month median". This drives the −8 rank penalty and withholds the discount.
- **Source B:** `AUDIT.md` §K and `labels.js`: "Honest reference prices" fails on "a struck-through price above anything the market ever charged for that product" (RRP or the shop's own 90-day high).
- **Chosen interpretation:** Keep both, under distinct names. `reference_price_unverified` is the offer-level flag from `fakeDiscount`. `honest_reference_prices` is the shop label check from `labels.js`, which uses the per-offer series from C-04.
- **Why:** Both are code (1) serving different purposes.
- **Backend impact:** Two functions and two tests. The UI copy names which one is meant.

## C-41 · Review sub-rating dimensions

- **Source A:** `REVIEWS.md` product "`sub_ratings` | value, quality, packaging, ease of use". For shops it lists "delivery speed, shipping cost, customer support, communication, product accuracy, returns / dispute handling".
- **Source B:** `DATABASE.md`: "`sub_ratings` (jsonb: shipping, communication, price, support, order)".
- **Chosen interpretation:** Use the REVIEWS.md dimension sets, one per review type, validated per type in a form request.
- **Why:** Domain doc (5) outranks DATABASE (6). The code carries no sub-ratings on seeded reviews.
- **Backend impact:** jsonb with a per-type schema.

## C-42 · Seed load order

- **Source A:** `ARCHITECTURE.md` "Seed load order (updated)" lists only seed, community, dose, orders, seo, intel, growth and commercial.
- **Source B:** The HTML `<script>` order is seed → community → geo → live → gamify → dose → orders → labels → network → labels.js → visibility seed → governance seed → visibility.js → governance.js → seo → intel → intel.js → growth → growth.js → commercial → commercial.js → addons → live.js → gamify.js → addons.js.
- **Chosen interpretation:** Importers follow the HTML order. `seed-orders` depends on the 27 markets from `seed-geo`. `seed-gamify` overrides `S.levels`. `seed-addons` extends `cx.placements`.
- **Why:** Code (1) outranks ARCHITECTURE (7).
- **Backend impact:** The order of `DatabaseSeeder` and one-off importer classes.

## C-43 · Creator coupons are not coupons

- **Source A:** `CREATORS.md`: at tier 2 a creator gets "a coupon code".
- **Source B:** `AUDIT.md` B7: "**Creator coupons are not real coupons.** Issuing one toasts and audits; it does not appear in `allCoupons()`".
- **Chosen interpretation:** Creator codes become real `coupons` rows (`source = creator`, `creator_id`), so the total-price engine and coupon validity apply to them.
- **Why:** AUDIT (3) records the gap. The documented intent (5) is a real coupon.
- **Backend impact:** One nullable FK and a creator attribution sub-id.

## C-44 · Housekeeping: `support.js`, demo accounts

- **Source A:** The brief lists `support.js` among the executable sources. `README.md` demo accounts are `demo@comparo.example` and `admin@comparo.example`.
- **Source B:** `support.js` is the generated React renderer runtime ("GENERATED from dc-runtime/src/*.ts — do not edit."), not a support domain. Support tickets live in `seed-intel.js` (`tickets = [`). The code logs in with `demo@comparo.app` / `demo1234` and `admin@comparo.app` / `admin1234`, as MODERATION.md also says.
- **Chosen interpretation:** `support.js` is not ported. Tickets come from `seed-intel.js`. No prototype credential is seeded in any non-local environment. Local demo users are created only by a local-only seeder with generated passwords.
- **Why:** Code (1) facts.
- **Backend impact:** Seeder environment guard.

---

## Appendix A · QA regression cases the backend must keep

These come from `QA.md` → "Regression checklist (never break)", the role sweep and the flows. Each is tied to the backend test that owns it. Theme switching and browser state persistence are frontend-only and stay in the React/Inertia test suite.

| # | QA case (QA.md) | Backend test that keeps it |
|---|---|---|
| 1 | Total-price computation | `TotalPriceTest`: price − best valid coupon + zone shipping, free-over threshold on the effective price, free-shipping coupon, minor units, per market. Uses golden values from `offerRow()` |
| 2 | Compliance filtering | `ComplianceFilteringTest`: status × surface matrix (offers, recommendations, alerts, sitemaps, programmatic pages, redirect, sponsorship), with market in every cache key (COMPLIANCE.md suite) |
| 3 | ComparoRank explainability | `RankingServiceTest`: breakdown present, parts plus visible penalties plus hidden count; the architecture test fails if `OfferContext` gains a commercial property |
| 4 | Coupon validity (invalid codes must not discount) | `CouponApplicationTest`: expired, invalid, other-market and below-minOrder coupons never reduce a total; success rate is published only at ≥ 12 reports |
| 5 | Affiliate attribution | `AffiliateRedirectTest`: click recorded (buffered), sub-id `{prefix}-{uuid}`, UTM params, compliance and suspension re-check, idempotent conversion ingest per (network, order id) |
| 6 | Role permissions (role sweep) | Policy tests per endpoint: anonymous cannot reach merchant or staff actions; merchant A cannot read merchant B's analytics or billing; merchants never see risk scores; staff actions check the permission |
| 7 | SEO head sync (title, canonical, robots, hreflang, JSON-LD) | SSR head tests per page type: canonical, robots from indexation rules, hreflang only for enabled locales, JSON-LD `AggregateRating` only with approved reviews |
| 8 | Route sweep: every internal link resolves; no empty `<main>` | Feature test crawling named routes. Empty states are legitimate (READINESS §1) |
| 9 | State pass: basket, alerts, saved, follows, compare survive refresh | Server persistence tests for saved, alerts, follows and lists. Basket and compare are backend-persisted for signed-in users |
| 10 | Consumer flows: search → product → filter → compare → save → alert → interstitial | Feature and E2E flow tests per QA.md |
| 11 | Merchant flow: feed health → match centre → offers → deal → reply → benchmarks → automations | Feature tests, merchant-scoped |
| 12 | Staff flow: approval → compliance → moderation → risk → anomaly → link health → SEO → automation → tasks | Feature tests with an audit-log assertion on every privileged action |

Guards from `AUDIT.md` that the backend suite must also keep:

- Product lists go through canonical resolution: merged products never render twice, and counts come from the deduplicated set (AUDIT §E′).
- `publicRows`: anomalous or non-positive rows never enter aggregates, badges or promoted deals.
- A denormalised number has one writer job plus a parity test against its source (READINESS §7).
- A label must publish what it does not mean, and revocations are keyed by id (AUDIT §K).
- A paid record and a measured record are never both sources of truth. Enrolment derives from measurement, and billing derives from enrolment (AUDIT §J).
- One clock: a record is stamped by the server clock and rendered relative to it (AUDIT §L).
- Synonyms expand the query and never raise the per-item floor (AUDIT §G).
