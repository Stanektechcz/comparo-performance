<?php

namespace App\Domain\Search\Contracts;

/**
 * The top visible entries across types for a typed prefix in one market.
 */
final readonly class SuggestResults
{
    /**
     * @param  list<SuggestItem>  $items  best first
     */
    public function __construct(
        public string $prefix,
        public string $market,
        public array $items,
    ) {}
}
