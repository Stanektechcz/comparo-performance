<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature flags (A-17)
    |--------------------------------------------------------------------------
    |
    | Config-backed wrapper: App\Domain\Platform\Features\FeatureFlags is the
    | only place that reads these keys. Default is on for every flag; flip
    | the env var to disable without a deploy.
    |
    */

    'merchant-feeds' => (bool) env('FEATURE_MERCHANT_FEEDS', true),

    'feed-url-fetch' => (bool) env('FEATURE_FEED_URL_FETCH', true),

    'matching-auto-publish' => (bool) env('FEATURE_MATCHING_AUTO_PUBLISH', true),

];
