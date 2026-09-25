<?php

namespace App\Domain\Compliance\Queries;

use App\Domain\Compliance\ComplianceDecision;
use App\Domain\Platform\Markets\MarketContext;
use App\Models\ProductComplianceRule;

/**
 * Resolves product × market compliance. A missing rule is `unknown`, never `allowed`.
 */
final class ComplianceResolver
{
    public function decide(int $productId, MarketContext $market): ComplianceDecision
    {
        return $this->decideMany([$productId], $market)[$productId];
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, ComplianceDecision>
     */
    public function decideMany(array $productIds, MarketContext $market): array
    {
        $rules = $market->countryId === null ? collect() : ProductComplianceRule::query()
            ->whereIn('product_id', $productIds)
            ->where('country_id', $market->countryId)
            ->get()
            ->keyBy('product_id');

        $decisions = [];
        foreach ($productIds as $productId) {
            $rule = $rules->get($productId);
            $decisions[$productId] = $rule === null
                ? ComplianceDecision::unreviewed($market->code)
                : new ComplianceDecision(
                    status: $rule->status,
                    marketCode: $market->code,
                    reason: $rule->reason,
                    source: $rule->source,
                    reviewedAt: $rule->reviewed_at?->toImmutable(),
                    hasExplicitRule: true,
                );
        }

        return $decisions;
    }
}
