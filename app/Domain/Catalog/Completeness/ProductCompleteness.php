<?php

namespace App\Domain\Catalog\Completeness;

final readonly class ProductCompleteness
{
    /**
     * @param  list<string>  $missing
     */
    public function __construct(
        public int $percent,
        public array $missing,
    ) {}

    /**
     * The 0–1 ratio fed to ComparoRank (derived from the rounded percentage, as in the prototype).
     */
    public function ratio(): float
    {
        return $this->percent / 100;
    }
}
