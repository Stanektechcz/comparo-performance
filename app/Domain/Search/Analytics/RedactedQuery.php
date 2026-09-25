<?php

namespace App\Domain\Search\Analytics;

/**
 * A search text as analytics store it: folded, redacted and capped
 * (QueryRedactor), with the sha256 of exactly that text.
 */
final readonly class RedactedQuery
{
    public function __construct(
        public string $text,
        public string $hash,
    ) {}
}
