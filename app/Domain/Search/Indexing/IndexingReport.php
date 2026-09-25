<?php

namespace App\Domain\Search\Indexing;

/**
 * What one indexing batch wrote.
 */
final readonly class IndexingReport
{
    public function __construct(
        public int $upserted,
        public int $deleted,
    ) {}

    public function plus(self $other): self
    {
        return new self($this->upserted + $other->upserted, $this->deleted + $other->deleted);
    }
}
