# Phase 4 design — reviews, orders & purchase verification

Status: **binding for Phase 4** (2026-09-26). Synthesised from the prototype analysis (reviewTrust
`intel.js:131-159`, reviewWeight / ratingOf HTML 12886-12925, deliveryStats HTML 16873-16894,
proof routes HTML 12376-12455, `seed-orders.js`) and the Laravel design analysis. Decisions D-08, D-13,
D-15, D-18, D-19, D-22, D-23, D-26, D-27 (ADR-0018) and contradictions C-12…C-14, C-34, C-41 apply;
autonomous decisions A-30…A-38 are in `docs/autonomy/OPEN-DECISIONS.md`.

**Exit flow:** an authenticated shopper submits a review → attaches proof (receipt now; click match after
Phase 5; forwarded e-mail behind a disabled flag) → verification decision → moderation → approval →
the public aggregate and the product/shop projection update (JSON-LD only above the minimum) → the
merchant is notified (database notification) and may reply; reports, votes, orders from evidence,
delivery events and measured delivery metrics work end to end.

## 1. Modules

| Namespace | Pure (parity, arch-tested) | IO |
|---|---|---|
| `App\Domain\Reviews` | `Credibility\{ReviewTrustInput, ReviewTrust, CredibilityLevel, ReviewWeight}`, `Aggregation\{RatingAggregator, RatingInput, RatingAggregate}`, `Abuse\{TextHeuristics, TextSimilarity, BurstDetector}`, `Moderation\ReviewTransitions` | Actions (SubmitReview, ModerateReview, CastVote, ReportContent, PostReply, RecomputeRating), Queries, Events, Listeners, Jobs |
| `App\Domain\Verification` | `Matching\ProofMatcher`, `ProofTransitions` | Contracts `ClickLedger` (Null until Phase 5), `InboundEmailProvider` (signed local simulator); Actions StartProof, DecideProof, StoreReceipt, PurgeReceipts |
| `App\Domain\Orders` | `Delivery\DeliveryStatsCalculator` (prototype variant), order/return/dispute transitions | Actions CreateOrderFromEvidence, RecordDeliveryEvent, OpenReturn, OpenDispute; Queries MerchantDeliveryMetrics |

Directions: Verification → Orders (orders only from evidence, D-15); Verification → Reviews only via the
after-commit `PurchaseProofDecided` event. Ranking and Trust never import Reviews — they read the
denormalised rating columns that Reviews projects. Reviews/Verification/Orders never use Commercial;
Affiliate only through `ClickLedger`. Credibility is frozen at decision time (`credibility_version`;
time passed in).

## 2. Schema (new migrations 2026_09_27_*; FKs restrict unless noted; money in minor units)

`reviews` (product XOR merchant subject, `purchased_from_merchant_id`, `user_id` nullOnDelete for erasure,
rating 1–5, title, body 20–5000, pros/cons json, recommends, status, status_reason_code,
verification_status/method, verified_order_id, credibility score/level/weight/version, helpful and
not-helpful counts, submitted/published/withdrawn timestamps; unique per user+subject), `review_sub_ratings`,
`review_signals` (hashed IP/UA with salt epoch, account age, prior reviews, duplicate-of, similarity,
burst; hashes nulled after 90 days), `review_moderation_events` (append-only, DSA statement of reasons),
`review_votes` (unique review+user), `content_reports` (reason list from MODERATION.md), `review_replies`
(unique per review, 24 h edit window), `purchase_proofs` (method, status, metadata-only evidence json,
HMAC order-reference hash and receipt sha256 for cross-account duplicates, receipt path + purged_at,
expires_at), `rating_aggregates` (projection source `aggregated`), `orders` (public ref, user nullable,
merchant, country, currency, item/shipping/total minor, placed_at, promised_days, status, source
`affiliate_conversion|purchase_proof|click_declaration`, evidence refs — no address, name or tracking),
`order_items`, `order_events` and `delivery_events` (append-only, no user_id, provisional 48 h),
`order_returns`, `order_disputes`, Laravel `notifications`. New columns on merchants/products must pass the
RankingPurityTest term scan.

## 3. State machines

Review: pending → approved | rejected; approved → flagged (report threshold, A-30) → approved | hidden;
hidden ↔ approved; author: pending|approved → withdrawn. Only `approved` is public.
Proof: pending → matched → verified; pending → needs_review → verified | rejected; unavailable (no
ledger), expired (30 d, receipt purged), withdrawn. Report: open → upheld | dismissed. Return: requested →
sent_back → refunded | rejected | cancelled. Dispute: open → resolved | expired (merchant response step
labelled "not yet available"). Order status is derived from events.

## 4. Surfaces

Public `routes/reviews.php`: product/shop review lists (paginated, deferred prop on the product page),
review forms (auth + verified e-mail), votes, reports. Account: `/account/reviews`, proof upload,
`/account/orders` (delivery report, return, dispute). Merchant (flag `merchant-reviews`, owner/manager
may reply; foreign ids 404): `/merchant/reviews`, reply, report, mark resolved — never score/status.
Staff: `/admin/moderation/reviews` + reports (`reviews.moderate`), `/admin/verification/proofs`
(`verification.decide`), receipt via 5-minute signed URL (audited, `nosniff`, CSP sandbox); staff who
are members of the reviewed merchant are refused. Webhook `POST /webhooks/inbound-email/{provider}`
(HMAC + timestamp + replay window; 404 while `verification-forwarded-email` is off). Limiters:
reviews 3/day + 10/h per user, votes 60/min, reports 20/h, receipts 5/h, replies 30/min, webhook 60/min.

## 5. Privacy & storage

New private `receipts` disk (serve false, random paths, pdf/jpg/png/webp ≤ 5 MB, sha256); receipts deleted
right after the decision or after 30 days; forwarded e-mail parsed in memory, never stored; nightly
`comparo:reviews:prune`; GDPR exporter/eraser contract per module (erasure nulls user_id → "Former member").

## 6. Notifications (database channel only; e-mail is Phase 6)

Queued after-commit: review published / rejected (with statement of reasons), proof decided, reply posted,
report decided, new review for merchant owners/managers. Ids and public text only.

## 7. Demo ratings (never faked, never blended)

Projection source `prototype_demo` (demo environments only) or `aggregated`. The first eligible real
approved review switches the projection to real reviews only (D-19, no blend). Presenters always pass the
source: demo lines are labelled; `AggregateRating` JSON-LD only for `aggregated` with ≥ 5 approved reviews
(A-31); below the minimum the UI says "Limited data (n)". Existing defects fixed here: `SeoPresenter` emits
AggregateRating for demo ratings; shop trust renders imported review counts unlabelled. Imported demo
ratings stay untouched so ranking/trust parity is unchanged.

## 8. Parity

Extend `tools/prototype-parity/reviews.mjs` (inputs, spamSignals, dupClusters, bursts, own-verified-proof
weight branch, merchant held-only aggregate per C-14) and `delivery.mjs` (all-markets case, synthetic
boundary sets n = 7/8, even/odd, p90 index, returns index). Exact parity for ReviewTrust, ReviewWeight,
RatingAggregator and DeliveryStatsCalculator (JsMath rounding). Not ported (documented): self-declared
`verifiedPurchase`, non-user-scoped click match, the merchant population blend, single-report unpublish.

## 9. Task graph

See `docs/autonomy/TASK-GRAPH.md` (Phase 4). Waves: {P4-02 schema, P4-03 pure engines + parity,
P4-04 platform config} → {P4-05 reviews domain, P4-06 verification + orders} → {P4-07 public/account UI,
P4-08 merchant reviews, P4-09 staff consoles, P4-10 notifications} → P4-11 E2E + isolation → P4-12 review
council, ADR-0019/0020, Gate C.
