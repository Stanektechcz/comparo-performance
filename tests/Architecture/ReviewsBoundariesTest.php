<?php

/**
 * Reviews, Verification and Orders boundaries
 * (docs/architecture/phase-4-reviews-orders.md §1): none of them may use
 * commercial data (plans, spend, campaigns), the affiliate context (the click
 * ledger is reached only through a Verification contract) or the HTTP layer.
 *
 * One arch() per subject and forbidden namespace: with several targets in one
 * expectation, Pest's negated `toUse` only fails when every target violates
 * the rule. All nine rules were proven to fail on 2026-09-26 with a planted
 * violation in a sub-namespace class of each subject. Pest only reports uses
 * of classes that exist: App\Domain\Commercial and App\Domain\Affiliate have
 * no classes yet, so the proof used temporary stubs there (then removed).
 */
foreach ([
    'App\Domain\Reviews',
    'App\Domain\Verification',
    'App\Domain\Orders',
] as $subject) {
    foreach ([
        'App\Domain\Commercial',
        'App\Domain\Affiliate',
        'App\Http',
    ] as $forbidden) {
        arch("{$subject} does not use {$forbidden}")
            ->expect($subject)
            ->not->toUse($forbidden);
    }
}
