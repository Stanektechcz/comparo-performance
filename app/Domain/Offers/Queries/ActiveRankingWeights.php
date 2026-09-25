<?php

namespace App\Domain\Offers\Queries;

use App\Domain\Offers\Ranking\RankingWeights;
use App\Models\RankingVersion;
use RuntimeException;

final class ActiveRankingWeights
{
    private ?RankingWeights $current = null;

    public function current(): RankingWeights
    {
        return $this->current ??= RankingVersion::query()
            ->with('weights')
            ->where('is_active', true)
            ->first()
            ?->toRankingWeights()
            ?? throw new RuntimeException('No active ComparoRank version is configured.');
    }
}
