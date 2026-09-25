<?php

/**
 * Scoring services are pure: immutable inputs, typed results, no database,
 * no facades, no clock, no randomness (docs/adr/0004, 0010).
 *
 * One arch() per namespace: with several targets in one expectation, Pest's
 * negated `toUse` only fails when every target violates the rule (verified
 * with a planted violation on 2026-09-25).
 */
foreach ([
    'App\Domain\Offers\Ranking',
    'App\Domain\Pricing\LandedPrice',
    'App\Domain\Pricing\MarketStats',
    'App\Domain\Pricing\History',
    'App\Domain\Pricing\Confidence',
    'App\Domain\Pricing\Anomalies',
    'App\Domain\Pricing\Currency\CurrencyConversion',
    'App\Domain\Merchants\Trust',
    'App\Domain\Merchants\Risk',
    'App\Domain\Catalog\Completeness',
    'App\Domain\Compliance\ComplianceStatus',
    'App\Domain\Compliance\ComplianceDecision',
    'App\Domain\Shared',
    'App\Domain\Shared\Text',
    'App\Domain\Matching\Engine',
    'App\Domain\Feeds\Mapping',
    'App\Domain\Feeds\Normalisation',
    'App\Domain\Feeds\Validation',
    'App\Domain\Search\Relevance',
    'App\Domain\Search\Query',
    'App\Domain\Search\Facets',
    'App\Domain\Search\Local',
    'App\Domain\Search\DidYouMean',
    'App\Domain\Search\DidYouMeanSuggestion',
] as $pureNamespace) {
    arch("{$pureNamespace} does not touch the database, facades, the clock or randomness")
        ->expect($pureNamespace)
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
}

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
        'App\Domain\Matching\Engine\FeedItemFacts',
        'App\Domain\Matching\Engine\CandidateProduct',
        'App\Domain\Matching\Engine\BrandAliasSet',
        'App\Domain\Matching\Engine\MatchPart',
        'App\Domain\Matching\Engine\MatchResult',
        'App\Domain\Matching\Engine\MatchingPolicy',
        'App\Domain\Feeds\Mapping\FieldMapping',
        'App\Domain\Feeds\Normalisation\NormalisationContext',
        'App\Domain\Feeds\Normalisation\NormalisedFeedItem',
        'App\Domain\Feeds\Validation\FeedIssue',
        'App\Domain\Feeds\Validation\RowRejection',
        'App\Domain\Search\Relevance\RelevanceWeights',
        'App\Domain\Search\Relevance\SynonymTable',
        'App\Domain\Search\Relevance\RelevanceHit',
        'App\Domain\Search\Relevance\FuzzyScore',
        'App\Domain\Search\Relevance\SynonymExpansion',
        'App\Domain\Search\Relevance\PrototypeRelevance',
        'App\Domain\Search\Query\SearchQuery',
        'App\Domain\Search\Query\SearchFilters',
        'App\Domain\Search\Query\NormalizedQuery',
        'App\Domain\Search\Query\QueryNormalizer',
        'App\Domain\Search\Facets\FacetValue',
        'App\Domain\Search\Facets\SearchFacets',
        'App\Domain\Search\Facets\FacetShaper',
        'App\Domain\Search\Local\SearchableEntry',
        'App\Domain\Search\Local\EntryAttributes',
        'App\Domain\Search\Local\MarketAttributes',
        'App\Domain\Search\Local\LocalSearchResult',
        'App\Domain\Search\Local\LocalQueryEvaluator',
        'App\Domain\Search\DidYouMean',
        'App\Domain\Search\DidYouMeanSuggestion',
    ])
    ->toBeReadonly();

/**
 * Matching\Engine may depend on nothing but the shared kernel
 * (docs/architecture/phase-2-feeds-matching.md §1): no feed, catalogue,
 * offer, commercial or persistence code can influence a match.
 */
arch('the matching engine depends only on the shared kernel')
    ->expect('App\Domain\Matching\Engine')
    ->not->toUse([
        'App\Models',
        'App\Http',
        'App\Domain\Accounts',
        'App\Domain\Catalog',
        'App\Domain\Commercial',
        'App\Domain\Affiliate',
        'App\Domain\Compliance',
        'App\Domain\Feeds',
        'App\Domain\Merchants',
        'App\Domain\Offers',
        'App\Domain\Platform',
        'App\Domain\Pricing',
        'App\Domain\Matching\Actions',
        'App\Domain\Matching\Queries',
        'Illuminate',
    ]);

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
