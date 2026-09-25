<?php

namespace App\Domain\Matching\Queries;

use App\Domain\Matching\Engine\MatchingPolicy;
use App\Domain\Matching\Exceptions\NoActiveMatchingPolicy;
use App\Models\MatchingPolicy as MatchingPolicyRow;

/**
 * Reads the single active matching policy (partial unique index
 * `matching_policies_single_active`). The result is kept for the lifetime of
 * this instance, so one feed run matches every row under the same policy.
 */
final class ActiveMatchingPolicy
{
    private ?ActivePolicy $current = null;

    /**
     * @throws NoActiveMatchingPolicy
     */
    public function current(): ActivePolicy
    {
        if ($this->current !== null) {
            return $this->current;
        }

        $row = MatchingPolicyRow::query()->where('is_active', true)->first();

        if ($row === null) {
            throw NoActiveMatchingPolicy::make();
        }

        return $this->current = new ActivePolicy(
            $row->id,
            MatchingPolicy::fromArray($row->version, $row->algorithm, $row->weights, $row->thresholds, $row->levels),
        );
    }
}
