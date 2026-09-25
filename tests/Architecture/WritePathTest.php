<?php

/**
 * Phase 2 write paths (docs/architecture/phase-2-feeds-matching.md §1, §6):
 * price history has one runtime writer, domain events carry ids and scalars
 * and fire after commit, and Offers/Pricing never reach into the feed
 * pipeline or the matcher.
 */
arch('price snapshots are written only by the Pricing context (and read by models and the demo importer)')
    ->expect('App\Models\PriceSnapshot')
    ->toOnlyBeUsedIn([
        'App\Domain\Pricing',
        'App\Models',
        'App\Domain\Platform\PrototypeImport',
    ]);

arch('offer and pricing events are final readonly after-commit messages')
    ->expect(['App\Domain\Offers\Events', 'App\Domain\Pricing\Events'])
    ->classes()
    ->toBeFinal()
    ->toBeReadonly()
    ->toImplement('Illuminate\Contracts\Events\ShouldDispatchAfterCommit');

/*
 * One arch() per target: with several targets, Pest's negated `toUse` fails
 * only when EVERY target uses the dependency.
 */
foreach (['App\Domain\Offers\Events', 'App\Domain\Pricing\Events'] as $namespace) {
    arch("{$namespace} carry ids and scalars, never models")
        ->expect($namespace)
        ->not->toUse(['App\Models', 'Illuminate\Database']);
}

/**
 * `ListingMatchStatus` is the cast type of merchant_products.match_status, so
 * LinkListing/PublishOffer read and write it; no other Matching symbol is allowed.
 */
foreach (['App\Domain\Offers', 'App\Domain\Pricing'] as $namespace) {
    arch("{$namespace} does not depend on the feed pipeline or the matcher")
        ->expect($namespace)
        ->not->toUse(['App\Domain\Feeds', 'App\Domain\Matching'])
        ->ignoring('App\Domain\Matching\ListingMatchStatus');
}

foreach (['App\Domain\Pricing\History\SnapshotPolicy', 'App\Domain\Pricing\Anomalies'] as $namespace) {
    arch("{$namespace} is pure")
        ->expect($namespace)
        ->not->toUse([
            'App\Models',
            'Illuminate\Database',
            'Illuminate\Support\Facades',
            'Illuminate\Support\Carbon',
            'Carbon\Carbon',
            'now',
            'today',
            'time',
        ]);
}

arch('publishing inputs and results are immutable')
    ->expect([
        'App\Domain\Offers\Actions\ListingObservation',
        'App\Domain\Offers\Actions\UpsertedListing',
        'App\Domain\Offers\Actions\OfferTerms',
        'App\Domain\Offers\Actions\PublishContext',
        'App\Domain\Offers\Actions\PublishResult',
        'App\Domain\Pricing\Anomalies\PriceAnomalyFinding',
    ])
    ->toBeReadonly();
