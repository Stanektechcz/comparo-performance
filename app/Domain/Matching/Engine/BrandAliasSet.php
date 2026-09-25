<?php

namespace App\Domain\Matching\Engine;

/**
 * Alternative spellings of one canonical brand name.
 */
final readonly class BrandAliasSet
{
    /**
     * @param  list<string>  $aliases
     */
    public function __construct(
        public string $canonicalBrandName,
        public array $aliases,
    ) {}
}
