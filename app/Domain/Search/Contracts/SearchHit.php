<?php

namespace App\Domain\Search\Contracts;

use App\Domain\Search\Local\SearchableType;

/**
 * One result reference. Scores are engine-specific: prototype relevance
 * points for the database engine, Meilisearch ranking scores (0–1) for
 * Meilisearch; only their order within one response is meaningful.
 */
final readonly class SearchHit
{
    public function __construct(
        public SearchableType $type,
        public int|string $id,
        public float $score,
    ) {}
}
