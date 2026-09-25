<?php

namespace App\Domain\Feeds\Queries;

use App\Models\FeedSource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * A merchant's feed sources, always scoped by merchant id: another merchant's
 * source id simply does not exist here (404, never 403).
 */
final class MerchantFeedSources
{
    /**
     * Sources with their latest run and current mapping (eager loaded, no N+1).
     *
     * @return LengthAwarePaginator<int, FeedSource>
     */
    public function paginate(int $merchantId, int $perPage = 20): LengthAwarePaginator
    {
        return FeedSource::query()
            ->where('merchant_id', $merchantId)
            ->with(['latestRun', 'currentMapping'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * @throws ModelNotFoundException<FeedSource> for a missing or foreign id
     */
    public function find(int $merchantId, int $sourceId): FeedSource
    {
        return FeedSource::query()
            ->where('merchant_id', $merchantId)
            ->with(['latestRun', 'currentMapping'])
            ->findOrFail($sourceId);
    }
}
