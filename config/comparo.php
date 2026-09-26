<?php

use App\Domain\Platform\Queues\LongRunningQueue;

return [

    /*
    |--------------------------------------------------------------------------
    | Markets
    |--------------------------------------------------------------------------
    |
    | A market is a destination country. It decides shipping, compliance and
    | the display currency; it is independent of the UI language.
    |
    */

    'default_market' => strtoupper((string) env('COMPARO_DEFAULT_MARKET', 'DE')),

    'market_cookie' => 'comparo_market',

    /*
    | Offers are compared in this currency. Display conversion to the
    | market currency uses a dated exchange rate and is indicative only.
    */
    'comparison_currency' => 'EUR',

    /*
    |--------------------------------------------------------------------------
    | Market launch, locales and default-market policy (ADR-0018)
    |--------------------------------------------------------------------------
    |
    | Callers: a future production market seeder (D-01, not yet built — the
    | demo importer PrototypeSnapshotImporter ignores this and keeps all 27
    | markets active for parity), the locale negotiator (D-02, live with
    | Phase 11) and any future IP-geolocation banner (D-24, no source is
    | contracted yet so this stays false). No code reads these keys today;
    | they document the decided values and become the source of truth when
    | each feature is built. Values are exactly the ADR-0018 decisions, not
    | invented behaviour.
    |
    */

    'markets' => [
        // D-01: the only markets a production seeder may activate. All 27
        // countries stay in the demo/import data; only DE and AT are
        // storefront + delivery active at first launch.
        'launch' => array_filter(array_map(
            'trim',
            explode(',', (string) env('COMPARO_LAUNCH_MARKETS', 'DE,AT')),
        )),
        // D-24: IP country is a suggestion banner only, never an automatic
        // switch, and stays off until a geolocation source is licensed.
        'ip_suggestion' => false,
    ],

    'locales' => [
        // D-02: UI locales shipped through Laravel lang files. Product data
        // stays in its source language; this list does not affect it.
        'enabled' => ['en', 'de'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency conversion (D-06)
    |--------------------------------------------------------------------------
    |
    | Read by the future ECB rate-import job (not yet built). Existing
    | App\Domain\Pricing\Currency\ExchangeRates and `comparison_currency`
    | above are unaffected.
    |
    */

    'fx' => [
        'source' => 'ecb',
        // Age of the last known rate before a display warning appears.
        'stale_warning_days' => (int) env('COMPARO_FX_STALE_WARNING_DAYS', 4),
        // Once the ECB import job exists, a rate older than this is unknown
        // (the offer drops out of cross-currency comparison, A-29). Null
        // until then: the demo/test rates are historical.
        'max_rate_age_days' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'offers_ttl' => (int) env('COMPARO_OFFERS_CACHE_TTL', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queues
    |--------------------------------------------------------------------------
    |
    | Long-running feed/matching/pricing jobs dispatch onto this connection.
    | QUEUE_LONG_CONNECTION wins; otherwise the default follows
    | QUEUE_CONNECTION: sync → sync (tests run the pipeline inline),
    | database → database-long, redis → redis-long (config/queue.php, whose
    | retry_after QUEUE_LONG_RETRY_AFTER exceeds the 900 s feed parser).
    | App\Domain\Platform\Queues\LongRunningQueue guards it at boot.
    |
    */

    'queues' => [
        'long_running_connection' => env(
            'QUEUE_LONG_CONNECTION',
            LongRunningQueue::defaultConnectionFor((string) env('QUEUE_CONNECTION', 'database')),
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Merchant feeds (docs/architecture/phase-2-feeds-matching.md §5, §7)
    |--------------------------------------------------------------------------
    |
    | `disk` must be a local, private disk: payloads are streamed to it and
    | parsed from the local path, and are never public or executed.
    |
    */

    'feeds' => [
        'disk' => env('COMPARO_FEEDS_DISK', 'local'),
        // A-09: more rejected rows than this ratio fails the run, nothing is published.
        'max_rejected_ratio' => (float) env('COMPARO_FEEDS_MAX_REJECTED_RATIO', 0.2),
        // A-10: reconciliation of SKUs missing from published runs (P2-11b).
        'missing_runs_before_deactivation' => 2,
        'unseen_days_before_deactivation' => 7,
        'mass_removal_ratio' => 0.5,
        // Sources with fewer live offers than this are exempt from the mass-removal guard.
        'mass_removal_min_offers' => 10,
        // A-11: an unchanged payload re-confirms last_seen_at / source_updated_at.
        'unchanged_refreshes_freshness' => (bool) env('COMPARO_FEEDS_UNCHANGED_REFRESHES_FRESHNESS', true),
        // feed_errors rows stored per run and code; run metrics keep the true totals.
        'max_errors_per_code' => 1000,
        'payload_retention_days' => 30,
        'item_retention_days' => 7,
        // Staged items of this many latest successful runs per source are never pruned.
        'item_retention_keep_successful_runs' => 2,
        'max_rows' => 200_000,
        'max_payload_bytes' => 100 * 1024 * 1024,
        'manual_run_cooldown_minutes' => 15,
        'consecutive_failures_before_error' => 3,
        'preview_rows' => 20,
        // Items per transaction in the match and publish stages.
        'pipeline_chunk' => 200,
        // Minutes a run may stay in one stage before the reaper fails it as STALLED.
        // fetching covers 3 attempts × 300 s timeout plus the 60/300/900 s backoff.
        'stage_deadlines_minutes' => [
            'queued' => 60,
            'fetching' => 45,
            'parsing' => 30,
            'normalizing' => 30,
            'matching' => 60,
            'publishing' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search (docs/architecture/phase-3-search.md §5)
    |--------------------------------------------------------------------------
    |
    | Indexing runs through the search_index_outbox: after-commit listeners
    | upsert rows, App\Domain\Search\Jobs\ProcessSearchOutbox (queue `search`,
    | timeout 60 s < the default connection's retry_after 90 s) drains them.
    |
    */

    'search' => [
        'indexing' => [
            'queue' => 'search',
            // Outbox rows taken per ProcessSearchOutbox run (one bulk write per entity type).
            'batch' => 200,
            // Products per indexing unit inside a run: each product runs one offer
            // comparison per active market, so products get a smaller unit (the run
            // stops taking units after ProcessSearchOutbox::WORK_SECONDS).
            'product_batch' => 25,
            // How long an in-progress full rebuild (comparo:search:reindex) keeps
            // its dual-write marker when the command dies before cleaning up.
            'rebuild_marker_ttl_seconds' => 21600,
            // Ids read and outbox rows written per statement when a merchant, brand,
            // category, ingredient or market change fans out to many products.
            'fan_out_chunk' => 500,
        ],
        // Search analytics retention (D-09 defaults; docs/privacy/data-retention.md).
        'analytics' => [
            'session_hash_days' => 90,
            'raw_retention_months' => 13,
            'click_window_minutes' => 30,
            'min_demand_sessions' => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Offer freshness (D-25)
    |--------------------------------------------------------------------------
    |
    | The prototype numbers, unchanged. ComparoRank
    | (app/Domain/Offers/Ranking/RankingService) is a pure service and must
    | stay byte-identical to the prototype fixtures, so it still reads its
    | own literal 48 h stale threshold rather than this key — wiring it
    | through the query layer without touching the pure service's inputs is
    | tracked as docs/autonomy/BACKLOG.md F-19. This key is the documented,
    | single source of truth for that future wiring.
    |
    */

    'offers' => [
        'freshness' => [
            'stock_high_hours' => 6,
            'stock_good_hours' => 24,
            'stale_hours' => 48,
            'price_fresh_hours' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Publication thresholds (D-26)
    |--------------------------------------------------------------------------
    |
    | Every minimum sample size a label, stat or signal needs before it is
    | published. Prototype numbers. Changing any of these goes through the
    | D-10 methodology changelog, not a casual env override.
    |
    */

    'thresholds' => [
        'delivered_orders' => 8,
        'coupon_reports' => 12,
        'demand_pledges' => 300,
        'label_reviews_min' => 5,
        'label_reviews_recent' => 4,
        'returns_fault' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Data retention (D-09)
    |--------------------------------------------------------------------------
    |
    | The engineering retention schedule. Existing `feeds.*` and
    | `search.analytics.*` keys above already enforce their values; these are
    | the remaining classes from the D-09 table. Requires DPO/Legal sign-off
    | before production (ADR-0018, "Still requires a human"); not an
    | operator-tunable env value.
    |
    */

    'retention' => [
        'receipt_max_days' => 30,
        'affiliate_click_months' => 24,
        'request_hash_days' => 90,
        'event_log_months' => 13,
        'audit_log_years' => 6,
        'account_erasure_days' => 30,
        'webhook_log_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Moderation (D-08, D-18)
    |--------------------------------------------------------------------------
    */

    'moderation' => [
        // D-08: internal targets only; not published until 4 consecutive
        // weeks of measured compliance ≥ 90 % (publish_sla flips it on).
        'targets_hours' => [
            'new_review' => 24,
            'reported_content' => 4,
            'reported_user' => 1,
        ],
        'publish_sla' => (bool) env('COMPARO_MODERATION_PUBLISH_SLA', false),
        // D-18: illegal-content removals are logged publicly within this window.
        'illegal_content_log_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Governance (D-18)
    |--------------------------------------------------------------------------
    */

    'governance' => [
        // Community juries are advisory; a named staff role always makes
        // the final, published decision. Changing this needs a new ADR.
        'juries_binding' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Compliance (D-11, D-12)
    |--------------------------------------------------------------------------
    */

    'compliance' => [
        // D-12: per-session self-declaration only; no date of birth, no
        // document. Retention of the record follows retention.request_hash_days.
        'age_gate' => [
            'method' => 'self_declaration',
            'scope' => 'session',
        ],
        // D-11: a compliance rule expires this many months after review and
        // resolves to `unknown` until re-reviewed.
        'rule_review_months' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fraud signals (D-23)
    |--------------------------------------------------------------------------
    */

    'fraud' => [
        // No client-side fingerprinting script, ever, without a new ADR.
        'client_fingerprinting' => false,
        'hash_salt_rotation_days' => (int) env('COMPARO_FRAUD_SALT_ROTATION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing (D-04, D-05)
    |--------------------------------------------------------------------------
    |
    | No live invoicing or charging in v1 (flags billing-live-invoicing,
    | billing-stripe in config/features.php, both off). The provider choice
    | needs a CFO decision plus D-04 tax sign-off, so it is not an
    | operator env override.
    |
    */

    'billing' => [
        'provider' => 'manual',
    ],

    /*
    |--------------------------------------------------------------------------
    | Affiliate (D-03, D-17)
    |--------------------------------------------------------------------------
    */

    'affiliate' => [
        // D-03: enabled network adapters. Direct needs no partner API;
        // Awin is built and tested against fixtures/sandbox only (flag
        // affiliate-network-api gates any live API adapter).
        'networks' => ['direct'],
        // D-17: disclosure interstitial auto-continue delay, cancellable.
        'interstitial_seconds' => (int) env('COMPARO_AFFILIATE_INTERSTITIAL_SECONDS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Visibility / advertising inventory (D-20)
    |--------------------------------------------------------------------------
    */

    'visibility' => [
        // Share of each surface withheld from sale; position 1 is never sold.
        'holdback_ratio' => (float) env('COMPARO_VISIBILITY_HOLDBACK_RATIO', 0.2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trust Score inputs (D-22)
    |--------------------------------------------------------------------------
    */

    'trust' => [
        // Returns are not a Trust Score input in v1. Changing this needs a
        // published D-10 methodology changelog entry.
        'returns_as_input' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Orders (D-15)
    |--------------------------------------------------------------------------
    */

    'orders' => [
        // Shopper-reported delivery/return/dispute events are provisional
        // for this many hours before they count toward a derived figure.
        'shopper_report_provisional_hours' => (int) env('COMPARO_ORDERS_PROVISIONAL_HOURS', 48),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo data
    |--------------------------------------------------------------------------
    |
    | The prototype's fictional catalogue and the demo personas (shopper,
    | merchant, staff) are only ever seeded outside production.
    |
    */

    'demo' => [
        'enabled' => (bool) env('COMPARO_DEMO_ACCOUNTS', false),
        'password' => env('COMPARO_DEMO_PASSWORD'),
        'snapshot' => database_path('data/prototype/seed-snapshot.json'),
    ],

];
