<?php

namespace App\Domain\Matching\Queries;

use App\Domain\Matching\MatchDecisionKind;
use App\Models\MatchingDecision;
use App\Models\MerchantProduct;

/**
 * The products a person rejected for one listing (BACKLOG F-05), derived from
 * the append-only matching_decisions history — no extra state is stored.
 *
 * Replaying the listing's decisions in order: a `rejected` decision adds its
 * `previous_product_id`; a later human link to that product (`manual` choose/
 * confirm or staff `rematch`) lifts the rejection again, because a person
 * explicitly re-opened the pair.
 */
final class RejectedProducts
{
    /**
     * @return list<int> ascending product ids
     */
    public function for(MerchantProduct $listing): array
    {
        if ($listing->current_matching_decision_id === null) {
            return [];
        }

        $rejected = [];

        $decisions = MatchingDecision::query()
            ->where('merchant_product_id', $listing->id)
            ->whereIn('kind', [MatchDecisionKind::Rejected, MatchDecisionKind::Manual, MatchDecisionKind::Rematch])
            ->orderBy('id')
            ->get(['kind', 'product_id', 'previous_product_id']);

        foreach ($decisions as $decision) {
            if ($decision->kind === MatchDecisionKind::Rejected && $decision->previous_product_id !== null) {
                $rejected[$decision->previous_product_id] = true;
            } elseif ($decision->kind !== MatchDecisionKind::Rejected && $decision->product_id !== null) {
                unset($rejected[$decision->product_id]);
            }
        }

        $ids = array_keys($rejected);
        sort($ids);

        return $ids;
    }
}
