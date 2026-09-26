# Configuration map (ADR-0018)

Every `comparo.*` key and feature flag that ADR-0018 decided, with its source decision and where it
lives. This is a map, not a rulebook: the values themselves, and the criteria for changing them, are
[ADR-0018](../adr/0018-business-decisions-baseline.md) and
[open-decisions.md](../architecture/open-decisions.md). A key with no reader yet documents the
decided value so it becomes the single source of truth when the feature is built — it does not, by
itself, change behaviour.

## `config/comparo.php`

| Key | Value | Source | Env override | Notes |
|---|---|---|---|---|
| `markets.launch` | `['DE', 'AT']` | D-01 | `COMPARO_LAUNCH_MARKETS` | Read by a future production market seeder only. The demo importer (`PrototypeSnapshotImporter`) keeps all 27 markets active; `DemoDataSeederTest` is unaffected. |
| `markets.ip_suggestion` | `false` | D-24 | — | Stays off until a geolocation source is licensed (human action). |
| `locales.enabled` | `['en', 'de']` | D-02 | — | UI locales only; product data stays in its source language. |
| `fx.source` | `'ecb'` | D-06 | — | |
| `fx.stale_warning_days` | `4` | D-06 | `COMPARO_FX_STALE_WARNING_DAYS` | |
| `fx.max_rate_age_days` | `null` | D-06 | — | Becomes `14` once the ECB import job exists (ADR-0018 table); the demo/test rates are historical. |
| `offers.freshness.*` | `stock_high_hours=6, stock_good_hours=24, stale_hours=48, price_fresh_hours=24` | D-25 | — | Documentation only for now. `RankingService` still reads its own literal `48` — it is a pure service (parity-critical) and this task's file scope did not include `app/Domain/Offers/Ranking`. Wiring is tracked as `docs/autonomy/BACKLOG.md` **F-19**. |
| `thresholds.delivered_orders` | `8` | D-26 | — | |
| `thresholds.coupon_reports` | `12` | D-26 | — | |
| `thresholds.demand_pledges` | `300` | D-26 | — | |
| `thresholds.label_reviews_min` / `label_reviews_recent` | `5` / `4` | D-26 | — | |
| `thresholds.returns_fault` | `3` | D-26 | — | |
| `retention.receipt_max_days` | `30` | D-09 | — | |
| `retention.affiliate_click_months` | `24` | D-09 | — | |
| `retention.request_hash_days` | `90` | D-09 | — | |
| `retention.event_log_months` | `13` | D-09 | — | |
| `retention.audit_log_years` | `6` | D-09 | — | |
| `retention.account_erasure_days` | `30` | D-09 | — | |
| `retention.webhook_log_days` | `30` | D-09 | — | |
| `moderation.targets_hours.*` | `new_review=24, reported_content=4, reported_user=1` | D-08 | — | |
| `moderation.publish_sla` | `false` | D-08 | `COMPARO_MODERATION_PUBLISH_SLA` | Flips on only after 4 consecutive weeks at ≥ 90 % measured compliance. |
| `moderation.illegal_content_log_hours` | `24` | D-18 | — | |
| `governance.juries_binding` | `false` | D-18 | — | Juries are advisory; changing this needs a new ADR. |
| `compliance.age_gate` | `{method: 'self_declaration', scope: 'session'}` | D-12 | — | |
| `compliance.rule_review_months` | `12` | D-11 | — | A compliance rule resolves to `unknown` after this many months unreviewed. |
| `fraud.client_fingerprinting` | `false` | D-23 | — | No client-side fingerprinting script, ever, without a new ADR. |
| `fraud.hash_salt_rotation_days` | `30` | D-23 | `COMPARO_FRAUD_SALT_ROTATION_DAYS` | |
| `billing.provider` | `'manual'` | D-05 | — | `ManualInvoiceProvider` only; needs a CFO decision to change. |
| `affiliate.networks` | `['direct']` | D-03 | — | Awin is built and tested against fixtures/sandbox only, behind `affiliate-network-api`. |
| `affiliate.interstitial_seconds` | `3` | D-17 | `COMPARO_AFFILIATE_INTERSTITIAL_SECONDS` | |
| `visibility.holdback_ratio` | `0.2` | D-20 | `COMPARO_VISIBILITY_HOLDBACK_RATIO` | Position 1 is never sold, independent of this ratio. |
| `trust.returns_as_input` | `false` | D-22 | — | Changing this needs a published D-10 methodology changelog entry. |
| `orders.shopper_report_provisional_hours` | `48` | D-15 | `COMPARO_ORDERS_PROVISIONAL_HOURS` | |
| `thresholds.rating_min_reviews` | `5` | A-31 | — | `AggregateRating` JSON-LD and "rated" summaries need at least this many real (aggregated) approved reviews; below it the UI says "Limited data (n)". |
| `trust.review_inputs` | `false` | A-32 | — | Review-derived Trust Score inputs stay off until the methodology owner signs (D-10); below `rating_min_reviews` the ranking fallback is the neutral 4.0, not zero. |
| `reviews.report_flag_threshold` | `3` | A-30 | — | A single report never unpublishes a review; it is flagged (hidden pending moderation) only once this many distinct reporters report it. |
| `reviews.daily_limit` / `hourly_limit` | `3` / `10` | §4 | — | Review submission rate limits, per user. |
| `reviews.body_min` / `body_max` | `20` / `5000` | §2 | — | `reviews.body` length bounds. |
| `reviews.reply_edit_hours` | `24` | §2 | — | A merchant reply may be edited for this many hours after posting. |
| `reviews.receipt_max_mb` | `5` | A-35 | — | Receipt upload size limit. |
| `reviews.receipt_types` | `['pdf','jpg','png','webp']` | A-35 | — | Accepted receipt MIME/extension types. |
| `verification.proof_expiry_days` | `30` | A-35 / A-38 | — | A proof never decided expires after this many days; matches `retention.receipt_max_days`. |
| `verification.signed_receipt_url_minutes` | `5` | §4 | — | Lifetime of the signed URL staff use to view a receipt. |
| `verification.inbound_replay_window_seconds` | `300` | D-27 | — | The forwarded-email webhook (flag `verification-forwarded-email`, off) rejects an HMAC + timestamp signature replayed outside this window. |

Existing keys already representing a D-xx decision (unchanged by this task): `default_market` (D-24),
`feeds.missing_runs_before_deactivation` / `unseen_days_before_deactivation` / `mass_removal_ratio` /
`mass_removal_min_offers` (D-25), `search.analytics.*` (D-09), `search.analytics.min_demand_sessions`
(D-26).

## `config/features.php` (`App\Domain\Platform\Features\Feature`)

Every flag defaults to `false` and none are client-visible (`Feature::clientVisible()`), per ADR-0018.
Each needs the human sign-off or contract named in ADR-0018 "Still requires a human" before it may
ever be turned on.

| Flag | Source | Env |
|---|---|---|
| `billing-live-invoicing` | D-04 | `FEATURE_BILLING_LIVE_INVOICING` |
| `billing-stripe` | D-05 | `FEATURE_BILLING_STRIPE` |
| `affiliate-network-api` | D-03 | `FEATURE_AFFILIATE_NETWORK_API` |
| `delivery-promise` | D-13 | `FEATURE_DELIVERY_PROMISE` |
| `delivery-promise-48h` | D-13 | `FEATURE_DELIVERY_PROMISE_48H` |
| `buyer-subscriptions` | D-14 | `FEATURE_BUYER_SUBSCRIPTIONS` |
| `xp-redemption` | D-14 | `FEATURE_XP_REDEMPTION` |
| `merchant-commission-display` | D-17 | `FEATURE_MERCHANT_COMMISSION_DISPLAY` |
| `self-serve-ad-booking` | D-20 | `FEATURE_SELF_SERVE_AD_BOOKING` |
| `developers-gate-exempt` | D-21 | `FEATURE_DEVELOPERS_GATE_EXEMPT` |
| `verification-forwarded-email` | D-27 | `FEATURE_VERIFICATION_FORWARDED_EMAIL` |
| `live-rooms` | D-28 | `FEATURE_LIVE_ROOMS` |
| `reviews-submission` | A-36 | `FEATURE_REVIEWS_SUBMISSION` |
| `merchant-reviews` | A-36 | `FEATURE_MERCHANT_REVIEWS` |

`reviews-submission` and `merchant-reviews` are the exception to "every flag defaults to false": A-36
defaults them **on** in `local`/`testing`/`demo` (`App\Domain\Platform\PrototypeImport\DemoEnvironment
::ALLOWED`) and **off** elsewhere, including production, until moderation staffing exists (D-08); the
env var still wins when set explicitly.

## `config/filesystems.php` — `receipts` disk (A-35)

A private, local-only disk for purchase-proof receipts (`storage/app/private/receipts`): `serve` is
`false` (no public route ever serves it) and `visibility` is `private`. Receipts are deleted right after
the verification decision or after `comparo.retention.receipt_max_days` (30), whichever is sooner; staff
view one only through a signed URL valid for `comparo.verification.signed_receipt_url_minutes`.

## Decisions represented elsewhere, not as new config

- D-07 (per-market offer prices), D-09 audit log, D-10 (ranking weight versioning/publish
  permission), D-16 (dashboard data-source presenters), D-19 (no rating imports), D-29 (permissions)
  are represented in schema, permissions or presenters/tests, not in `config/comparo.php` — see the
  ADR-0018 table's "Config / flag / data" column.
- D-01's production market seeder and D-25's `RankingService` wiring (F-19) are the two places a
  documented key here has no reader yet.
