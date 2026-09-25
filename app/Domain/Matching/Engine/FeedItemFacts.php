<?php

namespace App\Domain\Matching\Engine;

/**
 * The facts of one merchant feed row the matcher looks at, as delivered
 * (raw, unnormalised). Null and the empty string both mean "not provided";
 * any other string, including "0", is a value.
 */
final readonly class FeedItemFacts
{
    public function __construct(
        public string $rawTitle,
        public ?string $ean = null,
        public ?string $brandRaw = null,
        public ?string $packRaw = null,
        public ?string $variantRaw = null,
    ) {}
}
