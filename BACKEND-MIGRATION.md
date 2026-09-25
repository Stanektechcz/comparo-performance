# Backend migration — Laravel implementation guide

The prototype is a complete, working specification. Every score, rule and flow already exists as
readable JavaScript; this document maps it onto Laravel so nothing has to be re-invented from a
screenshot.

**Read these first:** `intel.js` (scoring), `growth.js` (opportunity engine), `commercial.js`
(revenue), and the `build*()` methods in `Comparo Performance.dc.html` (view models). Those four
are the specification.

---

## Stack

| Concern | Choice | Why |
|---|---|---|
| Framework | Laravel 11 | |
| Database | PostgreSQL 16 | partial indexes, `jsonb` for feed payloads, window functions for price stats |
| Search | Meilisearch | typo tolerance and synonyms are load-bearing here (`kreatin` = `creatine`) |
| Cache / queues | Redis | score cache, click buffer, rate limits |
| Queue worker | Horizon | feed imports, matching, price aggregation are all async |
| Scheduler | Laravel Scheduler | nightly aggregates, sitemaps, alert evaluation |
| Frontend | Inertia + Vue, or keep the SPA and serve JSON | routes already match `API-ENDPOINTS.md` |
| Files | S3-compatible | receipts, merchant feeds, invoice PDFs |
| Mail | Postmark / Mailgun | transactional **and** inbound parsing for verification |
| Payments | Stripe first, abstracted | never hard-code a provider — see `BillingProvider` below |

---

## Domain model

### Catalogue

```php
Product          id, slug, name, brand_id, category_id, ean, gtin, package_size, unit,
                 servings, variant, description, canonical_id (nullable, self-ref for merges),
                 compliance jsonb, created_at, updated_at, deleted_at
Brand            id, slug, name, aliases jsonb        // alternate-spelling resolution
Category         id, slug, name, parent_id
Ingredient       id, slug, name, aliases jsonb
IngredientProduct product_id, ingredient_id, amount_mg, per_serving   // ← see "dose the ingredients"
Merchant         id, slug, name, domain, country, verified_at, partner, trust_score,
                 zones jsonb, affiliate jsonb, plan_id, commercial_status
Offer            id, product_id, merchant_id, price, currency, shipping, availability,
                 stock_confidence, url, last_seen_at, match_confidence, flags jsonb
OfferPriceHistory offer_id, observed_on (date), price, shipping, availability
                 // PARTITION BY RANGE (observed_on) — this table dominates the row count
```

**One product, many offers.** The canonical product graph is the moat: merchant listings resolve to
one `Product` through EAN/GTIN, brand, normalised title, variant and package size. Without it there
is no cross-shop price history and no comparison.

`canonical_id` implements merges: the merged record keeps its row (URLs must keep resolving), is
excluded from indexes, and `301`s to the survivor.

### Trust and reviews

```php
Review           id, user_id, type (product|merchant), target_id, rating, title, body,
                 pros jsonb, cons jsonb, status, verified_purchase, verify_method,
                 credibility_score, weight, created_at
PurchaseProof    id, user_id, review_id, route (click|email|receipt), status,
                 evidence jsonb, decided_by, decided_at
                 // evidence: {click_id} | {sender_domain, order_date, total} | {file_path}
ReviewVote       review_id, user_id, helpful
Dispute          id, merchant_id, review_id, state, opened_at, resolved_at
```

`weight` is denormalised from `credibility_score` so the aggregate is a plain weighted average in
SQL rather than a per-request computation:

```sql
SELECT round(sum(rating * weight) / nullif(sum(weight), 0), 1) AS avg,
       count(*) AS total, count(*) FILTER (WHERE verified_purchase) AS verified
FROM reviews WHERE type = ? AND target_id = ? AND status = 'approved';
```

### Affiliate and commercial

```php
AffiliateClick   id, user_id, session_id, merchant_id, product_id, placement, country,
                 device, subid, campaign_id, referrer, created_at
                 // PARTITION BY RANGE (created_at) — highest-volume table
AffiliateConversion id, click_id, network, external_id, amount, commission, status, reported_at
Plan / PlanVersion / FeatureEntitlement / Subscription / SubscriptionChange
BillingProfile / Invoice / InvoiceItem / Payment / CreditNote / CommercialDiscount
SponsoredCampaign / CampaignCreative / CampaignOrder / PlacementInventory
CommercialOpportunity / Renewal / ApiKey / ApiUsage / WebhookEndpoint
```

Full field lists are in the prototype's `seed-commercial.js`; the shapes there are the migration.

---

## Scoring services

Each maps to one file in the prototype. Keep them as **pure services** with no Eloquent access in
the scoring method itself — pass a DTO in, get a score out. That is what makes them testable and
what lets you move them behind a queue later.

| Service | Prototype source | Runs |
|---|---|---|
| `RankingService` | `intel.js → comparoRank()` | on offer write; cached per (offer, country) |
| `TrustService` | `intel.js → trust()` | nightly + on review/complaint/feed event |
| `FraudService` | `intel.js → reviewTrust(), riskScore()` | on review create, nightly sweep |
| `MatchingService` | `intel.js → matchScore()` | on feed import, per unmatched row |
| `PricingService` | `intel.js → priceStats(), anomaly(), priceConfidence()` | on price write |
| `DealService` | `intel.js → dealScore(), couponMeta()` | on deal/coupon change |
| `RecommendationService` | `intel.js → related()` | nightly precompute into `product_relations` |
| `PersonalizationService` | `buildForYou()` | request-time, from the event stream |
| `GrowthService` | `growth.js` | nightly; opportunities are derived, never stored by hand |
| `CommercialService` | `commercial.js` | on invoice/subscription/campaign write |
| `AutomationService` | `intel.js` rules + `growth.js` | scheduled evaluation |

### Worked example — ComparoRank

```php
final class RankingService
{
    public function score(OfferContext $ctx): RankResult
    {
        $f = [
            'price'        => $ctx->anomaly ? 0.0
                              : $this->clamp(1 - (($ctx->total / max($ctx->marketMin, 1)) - 1) / 0.35),
            'trust'        => $ctx->trustScore / 100,
            'delivery'     => $this->clamp(1 - ($ctx->deliveryDays - 1) / 6),
            'freshness'    => $this->clamp(1 - $ctx->feedAgeHours / 72),
            'availability' => $ctx->stockConfidence,
            'rating'       => $ctx->rating / 5,
            'shipping'     => $this->clamp(1 - $ctx->shipping / max($ctx->marketMedianShipping, 1)),
            'completeness' => $ctx->dataCompleteness,
        ];
        // weights are configuration, not constants — the Ranking Lab writes them
        $w = config('ranking.weights');
        $score = 0; $breakdown = [];
        foreach ($f as $k => $v) {
            $pts = round($v * $w[$k]);
            $score += $pts;
            $breakdown[] = ['factor' => __("ranking.$k"), 'points' => $pts];
        }
        if ($ctx->feedAgeHours > 48) { $score -= 10; $breakdown[] = ['factor' => __('ranking.stale'), 'points' => -10]; }
        return new RankResult(min(100, max(0, $score)), $breakdown);
    }
}
```

Three rules that must survive the port:

1. **Commission is not an input.** No affiliate field may enter this method. Enforce it with a test
   that fails if `OfferContext` grows a commercial property.
2. **Every score is explainable.** `breakdown` is not optional — the UI renders it under
   "Why this rank?" and the transparency claim depends on it.
3. **Flagged offers score 0 on price.** An untrusted number can never win a position.

---

## Queues

```php
ImportMerchantFeed      → ParseFeed → MatchOffers → RecomputePrices → RecomputeRanks
EvaluatePriceAlerts     // nightly: compare each alert target against today's best total
RecomputeTrustScores    // nightly
DetectReviewFraud       // on create + nightly sweep
GenerateSitemaps        // nightly
EvaluateAutomations     // hourly
ReconcileAffiliate      // daily, per network
IssueInvoices           // daily, on billing anniversary
FlushClickBuffer        // every 10s, Redis → Postgres batch insert
```

Alert evaluation is the one the prototype never implemented (see AUDIT.md B8). It is a simple
nightly job: for each active alert, compare the target against the current best total for that
product and country; if met, create a notification and mark it triggered.

---

## Events

```php
OfferUpdated  PriceChanged  ProductMatched  ProductsMerged
ReviewCreated  ReviewApproved  PurchaseProofDecided
MerchantRiskChanged  MerchantVerified  FeedImported  FeedFailed
DealExpired  CouponReported
MerchantSubscribed  PlanChanged  InvoiceIssued  InvoicePaid
CampaignActivated  CampaignCompleted  RenewalApproaching
AffiliateCommissionApproved  ApiUsageLimitReached
```

Listeners recompute derived state; nothing recomputes inline in a controller.

---

## Caching

| Key | TTL | Invalidated by |
|---|---|---|
| `offers:{product}:{country}:{currency}` | 5 min | `OfferUpdated`, `PriceChanged` |
| `rank:{offer}:{country}` | 1 h | `OfferUpdated`, weight change |
| `trust:{merchant}` | 24 h | review, complaint, feed event |
| `pricestats:{product}` | 6 h | `PriceChanged` |
| `sitemap:*` | 24 h | nightly regeneration |

The prototype's per-render memo cache becomes Redis. Cache keys must include country and currency —
a cached price without them is a bug that ships silently.

---

## Performance targets

| Path | Target |
|---|---|
| `GET /products/{slug}/offers` | < 120 ms p95 |
| `GET /search/suggest` | < 80 ms p95 |
| `GET /go/...` redirect | < 50 ms p95 |
| Feed import, 10k rows | < 60 s |
| Nightly full rank recompute | < 15 min |

The redirect is the revenue path. Buffer the click write; never block the redirect on a database
insert.

---

## Security and privacy

* **Authorisation by policy, not by controller condition.** Merchant scoping needs a global scope
  plus a policy test per endpoint — "merchant A cannot read merchant B" deserves its own test file.
* **Never store raw verification emails.** Parse, extract three fields, discard.
* **No card data touches the application.** Stripe Elements / Checkout only.
* **No health profile.** Personalisation may use country, saves, follows, searches and comparisons.
  It may never infer a condition, and there is no schema for one on purpose.
* **Internal signals stay internal.** Risk scores, fraud signals and commercial terms are excluded
  at the API resource layer.
* **GDPR:** export covers reviews, posts, lists, follows, alerts, preferences and community
  activity. Erase anonymises retained community content rather than deleting threads other people
  replied to.

---

## Billing provider abstraction

```php
interface BillingProvider {
    public function createCustomer(Merchant $m): string;
    public function createSubscription(Merchant $m, PlanVersion $p, BillingCycle $c): Subscription;
    public function changePlan(Subscription $s, PlanVersion $p): ProrationResult;
    public function cancel(Subscription $s, bool $atPeriodEnd): void;
    public function issueInvoice(Invoice $i): void;
    public function handleWebhook(Request $r): void;
}
```

Ship `StripeBillingProvider` and `ManualInvoiceProvider`. Enterprise contracts are frequently
invoiced manually — if the first implementation assumes Stripe everywhere, that requirement becomes
a rewrite.

**Tax is not a feature you implement from a prototype.** The prototype models tax-exclusive,
tax-inclusive, VAT rate and reverse charge as *states*. Real VAT determination, VIES validation and
OSS reporting need a specialist service and jurisdiction review before invoicing anyone.

---

## Build order

1. **Catalogue + offers + total price.** Product, Merchant, Offer, price history, `RankingService`.
   The product page with a working offer table is the whole product in miniature — if it is right,
   everything else is addition.
2. **Feed import + matching.** Without it the catalogue cannot grow.
3. **Search.** Meilisearch, synonyms, zero-result logging.
4. **Reviews + verification.** Click match first — it needs only the click table you already have.
5. **Affiliate redirect + conversion webhooks.** Revenue path.
6. **Accounts, saves, alerts.** Alert evaluation as a nightly job.
7. **Merchant dashboard.** Feed health, matching, analytics, deals.
8. **Staff consoles.** Moderation, compliance, risk.
9. **Commercial.** Plans, entitlements, subscriptions, invoices.
10. **Growth.** Derived; it reads everything above and writes only tasks.

---

## What the prototype does not settle

Named honestly so nobody assumes it was decided (full list in PROTOTYPE-LIMITATIONS.md and AUDIT.md):

* **No `Order` entity.** Verification, delivery reliability and disputes all want one anchor.
  Introduce it early — it removes three seeded figures and unblocks measured delivery times.
* **Ingredients carry no dose.** `amount_mg` per serving is in the schema above because price per
  gram of active ingredient is the comparison nobody else in this category can make. The prototype
  cannot do it; the backend should.
* **Per-offer price history is partial.** Fake-discount detection currently reasons from a subset.
  Record every offer's price daily from day one — this data cannot be backfilled.
* **Tax, invoicing and contract law** need specialist review, not a port.
* **Seeded analytics.** Roughly a third of Growth and Commercial figures are scenario constants.
  Each is marked in the prototype; none should be carried into production as if measured.
