<?php

namespace App\Domain\Search\Queries;

use App\Domain\Compliance\ComplianceDecision;
use App\Models\Product;

/**
 * A product hit that is still listed and not blocked in the market, with
 * the compliance decision it was re-checked against (never `blocked`;
 * `unknown` means informational prices only).
 */
final readonly class VisibleProduct
{
    public function __construct(
        public Product $product,
        public ComplianceDecision $decision,
    ) {}
}
