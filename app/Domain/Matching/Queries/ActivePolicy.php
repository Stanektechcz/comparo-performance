<?php

namespace App\Domain\Matching\Queries;

use App\Domain\Matching\Engine\MatchingPolicy;

/**
 * The active matching_policies row: its id (stored on every decision) and the
 * engine policy rebuilt from its JSON columns.
 */
final readonly class ActivePolicy
{
    public function __construct(
        public int $id,
        public MatchingPolicy $policy,
    ) {}
}
