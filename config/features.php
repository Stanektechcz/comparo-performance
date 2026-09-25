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

];
