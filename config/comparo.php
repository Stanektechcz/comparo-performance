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
