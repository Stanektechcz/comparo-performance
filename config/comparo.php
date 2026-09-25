<?php

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
