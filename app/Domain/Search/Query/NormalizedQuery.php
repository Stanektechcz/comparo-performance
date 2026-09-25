<?php

namespace App\Domain\Search\Query;

/**
 * A query after the single normalisation step every search surface shares.
 */
final readonly class NormalizedQuery
{
    /**
     * @param  string  $raw  the text as received
     * @param  string  $trimmed  JavaScript-trimmed once; used for every entity type
     * @param  string  $folded  `TextFold::fold($trimmed)` (prototype `nq`)
     * @param  bool  $searchable  whether the trimmed text reaches the minimum length
     */
    public function __construct(
        public string $raw,
        public string $trimmed,
        public string $folded,
        public bool $searchable,
    ) {}
}
