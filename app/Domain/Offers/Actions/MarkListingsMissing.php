<?php

namespace App\Domain\Offers\Actions;

use App\Domain\Offers\ListingStatus;
use App\Models\MerchantProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Listing lifecycle write of reconciliation (§7, D-25): the listings of a
 * feed source that were not seen in a run become `missing`, in one bulk
 * UPDATE. The Offers context owns merchant_products writes; what counts as a
 * published run is the caller's (the feed pipeline's) knowledge, passed in as
 * a subquery of run ids.
 *
 * `missing_run_count` is DERIVED, never incremented: the number of published
 * runs after the listing's last sighting (`last_seen_run_id`), so a retried
 * reconciliation writes the same value (idempotent).
 */
final class MarkListingsMissing
{
    /**
     * @param  QueryBuilder  $publishedRunIds  a query selecting one `id` column: the source's
     *                                         published runs up to and including `$runId`
     * @return int listings written
     */
    public function handle(int $feedSourceId, int $runId, QueryBuilder $publishedRunIds): int
    {
        $publishedRunsSinceLastSeen = DB::query()
            ->selectRaw('count(*)')
            ->fromSub($publishedRunIds, 'published_runs')
            ->whereRaw('published_runs.id > COALESCE(merchant_products.last_seen_run_id, 0)');

        return self::unseenListings($feedSourceId, $runId)->toBase()->update([
            'status' => ListingStatus::Missing->value,
            'missing_run_count' => $publishedRunsSinceLastSeen,
        ]);
    }

    /**
     * Active or missing listings of the source whose last sighting is not the given run.
     *
     * @return Builder<MerchantProduct>
     */
    public static function unseenListings(int $feedSourceId, int $runId): Builder
    {
        return MerchantProduct::query()
            ->where('feed_source_id', $feedSourceId)
            ->whereIn('status', [ListingStatus::Active->value, ListingStatus::Missing->value])
            ->where(static fn (Builder $query) => $query->whereNull('last_seen_run_id')->orWhere('last_seen_run_id', '!=', $runId));
    }
}
