<?php

/**
 * Asserts the ADR-0018 `comparo.*` config values match the decided values in
 * the ADR-0018 table (docs/adr/0018-business-decisions-baseline.md) and
 * docs/architecture/open-decisions.md. This does not assert behaviour — most
 * of these keys have no reader yet (docs/development/configuration.md) — only
 * that the documented decision is what config actually holds.
 */
it('holds the ADR-0018 decided values', function (string $key, mixed $expected) {
    expect(config($key))->toBe($expected);
})->with([
    // D-01
    'markets.launch' => ['comparo.markets.launch', ['DE', 'AT']],
    // D-24
    'markets.ip_suggestion' => ['comparo.markets.ip_suggestion', false],
    // D-02
    'locales.enabled' => ['comparo.locales.enabled', ['en', 'de']],
    // D-06
    'fx.source' => ['comparo.fx.source', 'ecb'],
    'fx.stale_warning_days' => ['comparo.fx.stale_warning_days', 4],
    'fx.max_rate_age_days' => ['comparo.fx.max_rate_age_days', null],
    // D-25
    'offers.freshness.stock_high_hours' => ['comparo.offers.freshness.stock_high_hours', 6],
    'offers.freshness.stock_good_hours' => ['comparo.offers.freshness.stock_good_hours', 24],
    'offers.freshness.stale_hours' => ['comparo.offers.freshness.stale_hours', 48],
    'offers.freshness.price_fresh_hours' => ['comparo.offers.freshness.price_fresh_hours', 24],
    // D-26
    'thresholds.delivered_orders' => ['comparo.thresholds.delivered_orders', 8],
    'thresholds.coupon_reports' => ['comparo.thresholds.coupon_reports', 12],
    'thresholds.demand_pledges' => ['comparo.thresholds.demand_pledges', 300],
    'thresholds.label_reviews_min' => ['comparo.thresholds.label_reviews_min', 5],
    'thresholds.label_reviews_recent' => ['comparo.thresholds.label_reviews_recent', 4],
    'thresholds.returns_fault' => ['comparo.thresholds.returns_fault', 3],
    // D-09
    'retention.receipt_max_days' => ['comparo.retention.receipt_max_days', 30],
    'retention.affiliate_click_months' => ['comparo.retention.affiliate_click_months', 24],
    'retention.request_hash_days' => ['comparo.retention.request_hash_days', 90],
    'retention.event_log_months' => ['comparo.retention.event_log_months', 13],
    'retention.audit_log_years' => ['comparo.retention.audit_log_years', 6],
    'retention.account_erasure_days' => ['comparo.retention.account_erasure_days', 30],
    'retention.webhook_log_days' => ['comparo.retention.webhook_log_days', 30],
    // D-08
    'moderation.targets_hours.new_review' => ['comparo.moderation.targets_hours.new_review', 24],
    'moderation.targets_hours.reported_content' => ['comparo.moderation.targets_hours.reported_content', 4],
    'moderation.targets_hours.reported_user' => ['comparo.moderation.targets_hours.reported_user', 1],
    'moderation.publish_sla' => ['comparo.moderation.publish_sla', false],
    // D-18
    'moderation.illegal_content_log_hours' => ['comparo.moderation.illegal_content_log_hours', 24],
    'governance.juries_binding' => ['comparo.governance.juries_binding', false],
    // D-12
    'compliance.age_gate' => ['comparo.compliance.age_gate', ['method' => 'self_declaration', 'scope' => 'session']],
    // D-11
    'compliance.rule_review_months' => ['comparo.compliance.rule_review_months', 12],
    // D-23
    'fraud.client_fingerprinting' => ['comparo.fraud.client_fingerprinting', false],
    'fraud.hash_salt_rotation_days' => ['comparo.fraud.hash_salt_rotation_days', 30],
    // D-05
    'billing.provider' => ['comparo.billing.provider', 'manual'],
    // D-03
    'affiliate.networks' => ['comparo.affiliate.networks', ['direct']],
    // D-17
    'affiliate.interstitial_seconds' => ['comparo.affiliate.interstitial_seconds', 3],
    // D-20
    'visibility.holdback_ratio' => ['comparo.visibility.holdback_ratio', 0.2],
    // D-22
    'trust.returns_as_input' => ['comparo.trust.returns_as_input', false],
    // D-15
    'orders.shopper_report_provisional_hours' => ['comparo.orders.shopper_report_provisional_hours', 48],
    // A-31
    'thresholds.rating_min_reviews' => ['comparo.thresholds.rating_min_reviews', 5],
    // A-32
    'trust.review_inputs' => ['comparo.trust.review_inputs', false],
    // A-30, §4
    'reviews.report_flag_threshold' => ['comparo.reviews.report_flag_threshold', 3],
    'reviews.daily_limit' => ['comparo.reviews.daily_limit', 3],
    'reviews.hourly_limit' => ['comparo.reviews.hourly_limit', 10],
    'reviews.body_min' => ['comparo.reviews.body_min', 20],
    'reviews.body_max' => ['comparo.reviews.body_max', 5000],
    'reviews.reply_edit_hours' => ['comparo.reviews.reply_edit_hours', 24],
    // A-35
    'reviews.receipt_max_mb' => ['comparo.reviews.receipt_max_mb', 5],
    'reviews.receipt_types' => ['comparo.reviews.receipt_types', ['pdf', 'jpg', 'png', 'webp']],
    // A-38, D-15
    'verification.proof_expiry_days' => ['comparo.verification.proof_expiry_days', 30],
    'verification.signed_receipt_url_minutes' => ['comparo.verification.signed_receipt_url_minutes', 5],
    // D-27
    'verification.inbound_replay_window_seconds' => ['comparo.verification.inbound_replay_window_seconds', 300],
]);

it('defaults every ADR-0018 feature flag to off', function (string $flag) {
    expect(config("features.{$flag}"))->toBeFalse();
})->with([
    'billing-live-invoicing',
    'billing-stripe',
    'affiliate-network-api',
    'delivery-promise',
    'delivery-promise-48h',
    'buyer-subscriptions',
    'xp-redemption',
    'merchant-commission-display',
    'self-serve-ad-booking',
    'developers-gate-exempt',
    'verification-forwarded-email',
    'live-rooms',
]);
