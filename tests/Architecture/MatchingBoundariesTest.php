<?php

/**
 * Matching application-layer boundaries (docs/architecture/phase-2-feeds-matching.md §1):
 * Matching may use Catalog data, Offers\Actions, Platform audit/features and
 * Shared — never the feed pipeline, commercial or affiliate data, the
 * Compliance context (the caller supplies a ComplianceHoldCheck) or HTTP.
 *
 * One arch() per namespace: with several targets in one expectation, Pest's
 * negated `toUse` only fails when every target violates the rule.
 */
foreach ([
    'App\Domain\Matching',
    'App\Domain\Matching\Actions',
    'App\Domain\Matching\Queries',
    'App\Domain\Matching\Events',
    'App\Domain\Matching\Contracts',
    'App\Domain\Matching\Exceptions',
] as $matchingNamespace) {
    foreach ([
        'App\Domain\Feeds',
        'App\Domain\Commercial',
        'App\Domain\Affiliate',
        'App\Domain\Compliance',
        'App\Http',
    ] as $forbidden) {
        arch("{$matchingNamespace} does not use {$forbidden}")
            ->expect($matchingNamespace)
            ->not->toUse($forbidden);
    }
}

arch('matching events are final readonly after-commit messages')
    ->expect('App\Domain\Matching\Events')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly()
    ->toImplement('Illuminate\Contracts\Events\ShouldDispatchAfterCommit');

arch('matching events carry ids and scalars, never models')
    ->expect('App\Domain\Matching\Events')
    ->not->toUse(['App\Models', 'Illuminate\Database']);

arch('matching action inputs, outcomes and queue rows are immutable')
    ->expect([
        'App\Domain\Matching\Actions\MatchContext',
        'App\Domain\Matching\Actions\MatchOutcome',
        'App\Domain\Matching\Actions\MatchingActor',
        'App\Domain\Matching\Actions\DecisionDraft',
        'App\Domain\Matching\Queries\ActivePolicy',
        'App\Domain\Matching\Queries\ProductSummary',
        'App\Domain\Matching\Queries\QueueListing',
        'App\Domain\Matching\Queries\DecisionHistoryEntry',
        'App\Domain\Matching\Queries\ConflictEntry',
        'App\Domain\Matching\Queries\CandidateEntry',
        'App\Domain\Matching\Queries\PreviewCandidate',
    ])
    ->toBeReadonly();

arch('the compliance hold check is an interface the caller implements')
    ->expect('App\Domain\Matching\Contracts\ComplianceHoldCheck')
    ->toBeInterface();
