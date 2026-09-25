<?php

namespace App\Domain\Search\Query;

use App\Domain\Search\Relevance\JsString;
use App\Domain\Shared\Text\TextFold;
use InvalidArgumentException;

/**
 * Trim once, fold, enforce the minimum length (DC `searchAll`: `q.trim()`,
 * `query.length < 2`, `H.norm`).
 *
 * Deliberate deviation (docs/architecture/phase-3-search.md §3): the
 * prototype scores products with the trimmed query but brands, shops,
 * categories and ingredients with the untrimmed one, so "creatine " ranks the
 * Creatine category at 54 instead of 94. Here the trimmed query is used for
 * every type.
 */
final readonly class QueryNormalizer
{
    public const int PROTOTYPE_MINIMUM_LENGTH = 2;

    public function __construct(
        private int $minimumLength = self::PROTOTYPE_MINIMUM_LENGTH,
    ) {
        if ($minimumLength < 1) {
            throw new InvalidArgumentException('The minimum query length must be at least 1.');
        }
    }

    public function normalize(string $raw): NormalizedQuery
    {
        $trimmed = JsString::trim($raw);

        return new NormalizedQuery(
            raw: $raw,
            trimmed: $trimmed,
            folded: TextFold::fold($trimmed),
            // Like the prototype: UTF-16 length of the trimmed text, before folding.
            searchable: JsString::length($trimmed) >= $this->minimumLength,
        );
    }
}
