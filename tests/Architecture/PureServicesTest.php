<?php

/**
 * Scoring services are pure: immutable inputs, typed results, no database,
 * no facades, no clock, no randomness (docs/adr/0004, 0010).
 */
arch('scoring services do not touch the database, facades, the clock or randomness')
    ->expect([
        'App\Domain\Offers\Ranking',
        'App\Domain\Pricing\LandedPrice',
        'App\Domain\Pricing\MarketStats',
        'App\Domain\Pricing\History',
        'App\Domain\Pricing\Confidence',
        'App\Domain\Pricing\Currency\CurrencyConversion',
        'App\Domain\Merchants\Trust',
        'App\Domain\Merchants\Risk',
        'App\Domain\Catalog\Completeness',
        'App\Domain\Compliance\ComplianceStatus',
        'App\Domain\Compliance\ComplianceDecision',
        'App\Domain\Shared',
    ])
    ->not->toUse([
        'App\Models',
        'Illuminate\Database',
        'Illuminate\Support\Facades',
        'Illuminate\Support\Carbon',
        'Carbon\Carbon',
        'now',
        'today',
        'time',
        'microtime',
        'rand',
        'mt_rand',
        'random_int',
    ]);

arch('scoring results and inputs are immutable')
    ->expect([
        'App\Domain\Offers\Ranking\RankingContext',
        'App\Domain\Offers\Ranking\RankingResult',
        'App\Domain\Offers\Ranking\RankingWeights',
        'App\Domain\Pricing\LandedPrice\LandedPrice',
        'App\Domain\Pricing\LandedPrice\LandedPriceInput',
        'App\Domain\Merchants\Trust\TrustSignals',
        'App\Domain\Merchants\Trust\TrustScore',
        'App\Domain\Shared\Money',
    ])
    ->toBeReadonly();

arch('no debugging helpers ship')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'var_dump', 'ray', 'die']);

arch('controllers never query scoring internals directly')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'App\Domain\Offers\Ranking\RankingService',
        'App\Domain\Pricing\LandedPrice\LandedPriceCalculator',
        'App\Domain\Merchants\Risk\RiskService',
    ]);
