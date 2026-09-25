<?php

namespace App\Domain\Search\Relevance;

use App\Domain\Search\Local\SearchableEntry;

/**
 * A scored entry. Scores are the prototype's integer relevance points and may
 * be zero or negative for non-product types (their entity offset is applied
 * after the inclusion test, exactly like DC `searchAll`).
 */
final readonly class RelevanceHit
{
    /**
     * @param  int  $position  0-based rank in relevance order
     */
    public function __construct(
        public SearchableEntry $entry,
        public int $score,
        public int $position,
    ) {}
}
