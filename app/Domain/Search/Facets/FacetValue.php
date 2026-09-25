<?php

namespace App\Domain\Search\Facets;

final readonly class FacetValue
{
    public function __construct(
        public string $value,
        public int $count,
    ) {}
}
