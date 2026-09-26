<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature flags (A-17)
    |--------------------------------------------------------------------------
    |
    | Config-backed wrapper: App\Domain\Platform\Features\FeatureFlags is the
    | only place that reads these keys. Every flag below defaults to on
    | explicitly; flip the env var to disable without a deploy. A flag with
    | no key here is OFF (the service fails closed), so a new Feature case
    | needs its entry.
    |
    */

    'merchant-feeds' => (bool) env('FEATURE_MERCHANT_FEEDS', true),

    'feed-url-fetch' => (bool) env('FEATURE_FEED_URL_FETCH', true),

    'matching-auto-publish' => (bool) env('FEATURE_MATCHING_AUTO_PUBLISH', true),

    /*
    |--------------------------------------------------------------------------
    | ADR-0018 business-decision flags
    |--------------------------------------------------------------------------
    |
    | Every flag D-xx decided is off at launch (A-17: a missing key already
    | fails closed; these keys make the "off" explicit). Each needs a human
    | sign-off or contract before it may ever be turned on — see ADR-0018
    | "Still requires a human" and the D-xx entry named below.
    |
    */

    // D-04: no live invoicing until a tax advisor signs off.
    'billing-live-invoicing' => (bool) env('FEATURE_BILLING_LIVE_INVOICING', false),

    // D-05: Stripe adapter built and tested in test mode only.
    'billing-stripe' => (bool) env('FEATURE_BILLING_STRIPE', false),

    // D-03: no network API adapter goes live without a signed contract.
    'affiliate-network-api' => (bool) env('FEATURE_AFFILIATE_NETWORK_API', false),

    // D-13: Promise tiers are built but collect no deposits while off.
    'delivery-promise' => (bool) env('FEATURE_DELIVERY_PROMISE', false),
    'delivery-promise-48h' => (bool) env('FEATURE_DELIVERY_PROMISE_48H', false),

    // D-14: buyer tiers exist as entitlements only; nothing is sold while off.
    'buyer-subscriptions' => (bool) env('FEATURE_BUYER_SUBSCRIPTIONS', false),
    'xp-redemption' => (bool) env('FEATURE_XP_REDEMPTION', false),

    // D-17: the merchant-specific commission rate stays hidden; only the
    // category range is ever shown while off.
    'merchant-commission-display' => (bool) env('FEATURE_MERCHANT_COMMISSION_DISPLAY', false),

    // D-20: staff book visibility placements manually while off.
    'self-serve-ad-booking' => (bool) env('FEATURE_SELF_SERVE_AD_BOOKING', false),

    // D-21: the commercial gate covers /developers until a data-customer
    // application exists to exempt it.
    'developers-gate-exempt' => (bool) env('FEATURE_DEVELOPERS_GATE_EXEMPT', false),

    // D-27: the forwarded-email verification route stays disabled; no
    // inbound mail infrastructure exists.
    'verification-forwarded-email' => (bool) env('FEATURE_VERIFICATION_FORWARDED_EMAIL', false),

    // D-28: live rooms stay disabled; history is still recorded.
    'live-rooms' => (bool) env('FEATURE_LIVE_ROOMS', false),

    /*
    |--------------------------------------------------------------------------
    | Phase 4 reviews & orders flags (A-36)
    |--------------------------------------------------------------------------
    |
    | A-36: reviews-submission and merchant-reviews default on only in
    | local/testing/demo (App\Domain\Platform\PrototypeImport\DemoEnvironment
    | ::ALLOWED), and off elsewhere (including production) until moderation
    | staffing exists (D-08). The env var still wins when set explicitly.
    |
    */

    // D-08: shopper review submission. Off in production until moderation
    // staffing exists.
    'reviews-submission' => (bool) env(
        'FEATURE_REVIEWS_SUBMISSION',
        in_array(env('APP_ENV', 'production'), ['local', 'testing', 'demo'], true),
    ),

    // Merchant reply/report surface for reviews (owner/manager only).
    'merchant-reviews' => (bool) env(
        'FEATURE_MERCHANT_REVIEWS',
        in_array(env('APP_ENV', 'production'), ['local', 'testing', 'demo'], true),
    ),

];
