<?php

namespace App\Domain\Compliance;

use App\Domain\Compliance\Queries\ComplianceResolver;
use App\Domain\Matching\Contracts\ComplianceHoldCheck;
use App\Domain\Platform\Markets\MarketContext;

/**
 * Compliance hold for matching decisions (A-19): a matched product is held
 * (linked but never published) when it is blocked — not allowed or
 * prescription only — in any market the listing is sold in.
 *
 * Unreviewed products are not held: `unknown` already shows prices without
 * purchase links (ADR-0007), and serialization re-checks compliance per market
 * on every response. Listings without a known market are likewise left to the
 * per-market serialization gate. Matching never queries Compliance itself.
 */
final readonly class MarketComplianceHold implements ComplianceHoldCheck
{
    /**
     * @param  list<MarketContext>  $markets
     */
    public function __construct(
        private ComplianceResolver $resolver,
        private array $markets,
    ) {}

    public function isBlocked(int $productId): bool
    {
        foreach ($this->markets as $market) {
            if ($this->resolver->decide($productId, $market)->status->isBlocked()) {
                return true;
            }
        }

        return false;
    }
}
