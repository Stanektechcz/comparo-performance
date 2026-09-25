<?php

namespace App\Domain\Compliance\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A product's compliance rule for some market was created, changed or
 * removed. Raised by the ProductComplianceRule model hook
 * (AppServiceProvider) and dispatched after the transaction commits; the
 * product's search document must be rebuilt with priority, since a blocked
 * product has to leave that market's results (invariant 2).
 */
final readonly class ComplianceRuleChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $productId,
    ) {}
}
