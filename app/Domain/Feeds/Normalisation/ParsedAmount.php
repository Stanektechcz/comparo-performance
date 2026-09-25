<?php

namespace App\Domain\Feeds\Normalisation;

/**
 * A decimal string parsed into minor units, plus the ISO code written next to
 * the number (e.g. "43,50 EUR"), if any.
 */
final readonly class ParsedAmount
{
    public function __construct(
        public int $minor,
        public ?string $currencyCode = null,
    ) {}
}
