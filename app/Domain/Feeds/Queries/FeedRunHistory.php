<?php

namespace App\Domain\Feeds\Queries;

use App\Models\FeedRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Runs of one merchant's feed source, newest first, scoped by merchant id.
 */
final class FeedRunHistory
{
    public function __construct(private readonly MerchantFeedSources $sources) {}

    /**
     * @return LengthAwarePaginator<int, FeedRun>
     *
     * @throws ModelNotFoundException for a missing or foreign source id
     */
    public function forSource(int $merchantId, int $sourceId, int $perPage = 20): LengthAwarePaginator
    {
        $source = $this->sources->find($merchantId, $sourceId);

        return FeedRun::query()
            ->where('merchant_id', $merchantId)
            ->where('feed_source_id', $source->id)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @throws ModelNotFoundException<FeedRun> for a missing or foreign run id
     */
    public function find(int $merchantId, int $runId): FeedRun
    {
        return FeedRun::query()->where('merchant_id', $merchantId)->findOrFail($runId);
    }
}
