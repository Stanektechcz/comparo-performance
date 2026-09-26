<?php

namespace App\Domain\Matching\Actions;

/**
 * Result of {@see RematchSuggestedListings}: how many demoted listings were
 * found and where they ended up (a dry run only fills `eligible`).
 */
final readonly class RematchSuggestedSummary
{
    public function __construct(
        public int $eligible,
        public bool $dryRun,
        public int $linked = 0,
        public int $held = 0,
        public int $suggested = 0,
        public int $unmatched = 0,
    ) {}
}
