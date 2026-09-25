<?php

use App\Domain\Search\Documents\BrandDocument;
use App\Domain\Search\Documents\CategoryDocument;
use App\Domain\Search\Documents\IngredientDocument;
use App\Domain\Search\Documents\MerchantDocument;
use App\Domain\Search\Documents\ProductDocument;
use Tests\Feature\Search\Support\CommercialTerms;

/**
 * Boundary tests for the Search context (docs/architecture/phase-3-search.md
 * §1): Search never reads commercial, affiliate or feed data and is never
 * reached directly from HTTP; index documents are built only by builders and
 * consumed only where indexing needs them; the engine port stays internal to
 * Search; document and contract DTOs are immutable; no document field name
 * is a commercial term (docs/adr/0004, shared with RankingPurityTest).
 *
 * The pure Search namespaces (Relevance, Query, Facets, Local, DidYouMean)
 * are already covered by PureServicesTest — not duplicated here.
 *
 * One arch() per subject namespace/class: with several SUBJECTS in one
 * negated `toUse` (or `toOnlyBeUsedIn`) expectation, Pest only fails when
 * every subject violates (verified with a planted violation on 2026-09-25,
 * see tests/Architecture/PureServicesTest.php and FeedJobsTest.php).
 */
foreach ([
    'App\Domain\Search',
    'App\Domain\Search\Console',
    'App\Domain\Search\Contracts',
    'App\Domain\Search\Documents',
    'App\Domain\Search\Engines',
    'App\Domain\Search\Facets',
    'App\Domain\Search\Indexing',
    'App\Domain\Search\Local',
    'App\Domain\Search\Queries',
    'App\Domain\Search\Query',
    'App\Domain\Search\Relevance',
    'App\Domain\Search\Settings',
] as $searchNamespace) {
    arch("{$searchNamespace} never reads commercial, affiliate or feed data and is never reached directly from HTTP")
        ->expect($searchNamespace)
        ->not->toUse([
            'App\Domain\Commercial',
            'App\Domain\Affiliate',
            'App\Domain\Feeds',
            'App\Http',
        ]);
}

/*
 * Documents are built only by builders (docs/architecture/phase-3-search.md
 * §1): each *Document DTO is consumed only where indexing needs it —
 * Documents (the builders themselves), Engines (the local engine reads
 * documents back for hydration), Indexing (writes documents to the engine)
 * and Console (the reindex command builds and writes them directly).
 */
foreach ([
    ProductDocument::class,
    BrandDocument::class,
    CategoryDocument::class,
    IngredientDocument::class,
    MerchantDocument::class,
] as $documentClass) {
    arch("{$documentClass} is only used by Search's Documents, Engines, Indexing and Console layers")
        ->expect($documentClass)
        ->toOnlyBeUsedIn([
            'App\Domain\Search\Documents',
            'App\Domain\Search\Engines',
            'App\Domain\Search\Indexing',
            'App\Domain\Search\Console',
        ]);
}

/*
 * The engine port stays internal to Search: SearchService, Indexing,
 * Console and the provider that binds it (App\Providers\SearchServiceProvider)
 * are the only allowed callers — never App\Http directly (HTTP controllers
 * go through SearchService).
 */
arch('the search engine contract is only used by Search itself and its service provider')
    ->expect('App\Domain\Search\Contracts\SearchEngine')
    ->toOnlyBeUsedIn([
        'App\Domain\Search',
        'App\Providers\SearchServiceProvider',
    ]);

arch('index documents are final and readonly')
    ->expect([
        ProductDocument::class,
        BrandDocument::class,
        CategoryDocument::class,
        IngredientDocument::class,
        MerchantDocument::class,
    ])
    ->toBeFinal()
    ->toBeReadonly();

arch('Contracts DTOs are readonly')
    ->expect('App\Domain\Search\Contracts')
    ->classes()
    ->toBeReadonly();

/*
 * No document field name is a commercial term: reused from
 * RankingPurityTest::COMMERCIAL_TERMS via CommercialTerms::pattern(), so
 * the two checks can never drift apart (invariant 1, docs/adr/0004).
 */
it('keeps every document field name free of commercial terms', function () {
    $pattern = CommercialTerms::pattern();

    foreach ([
        ProductDocument::class,
        BrandDocument::class,
        CategoryDocument::class,
        IngredientDocument::class,
        MerchantDocument::class,
    ] as $documentClass) {
        $fields = array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->getName(),
            (new ReflectionClass($documentClass))->getConstructor()?->getParameters() ?? [],
        );

        expect($fields)->not->toBeEmpty("{$documentClass} declares constructor properties")
            ->and(preg_grep($pattern, $fields))->toBe([], "{$documentClass} has a commercial-term field");
    }
});
