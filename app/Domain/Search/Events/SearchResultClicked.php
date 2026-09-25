<?php

namespace App\Domain\Search\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * An accepted, first click on a result of a recorded search
 * (RecordSearchClick). `entityType` is a SearchEntityType value, `position`
 * is 1-based within the displayed results.
 */
final readonly class SearchResultClicked implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $searchId,
        public string $entityType,
        public int $entityId,
        public int $position,
    ) {}
}
