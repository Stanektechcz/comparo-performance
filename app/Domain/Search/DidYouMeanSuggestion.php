<?php

namespace App\Domain\Search;

use App\Domain\Search\Local\SearchableEntry;

/**
 * A spelling suggestion: an entity label close to the query.
 */
final readonly class DidYouMeanSuggestion
{
    public function __construct(
        public SearchableEntry $entry,
        public int $score,
    ) {}

    public function label(): string
    {
        return $this->entry->name;
    }
}
