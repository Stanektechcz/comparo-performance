<?php

namespace App\Domain\Search\Analytics;

use DateTimeImmutable;

/**
 * A result click reported by the search page (POST /search/clicks).
 * `entityType` is a SearchableType value (`shop` for merchants); `position`
 * is 1-based within the displayed results. The session id is only used to
 * recompute the search's session hash and is never stored.
 */
final readonly class SearchClickInput
{
    public function __construct(
        public string $searchId,
        public string $entityType,
        public int $entityId,
        public int $position,
        public ?string $sessionId,
        public DateTimeImmutable $clickedAt,
    ) {}
}
