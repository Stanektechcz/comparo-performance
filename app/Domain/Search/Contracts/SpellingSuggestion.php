<?php

namespace App\Domain\Search\Contracts;

use App\Domain\Search\Local\SearchableType;

/**
 * A did-you-mean label: an entity name close to a query that found nothing
 * visible in the market.
 */
final readonly class SpellingSuggestion
{
    public function __construct(
        public SearchableType $type,
        public int|string $id,
        public string $label,
        public float $score,
    ) {}
}
