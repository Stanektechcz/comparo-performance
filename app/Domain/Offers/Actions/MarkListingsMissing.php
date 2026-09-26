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
 * reconciliation writes the same value (idempotent). It is clamped at
 * {@see self::MAX_MISSING_RUN_COUNT}, the maximum of the unsigned tinyint
 * column, so a listing missing for hundreds of runs never overflows it (F-09).
 */
final class MarkListingsMissing
{
    /** Maximum of merchant_products.missing_run_count (unsigned tinyint). */
    public const int MAX_MISSING_RUN_COUNT = 255;

    /**
     * @param  QueryBuilder  $publishedRunIds  a query selecting one `id` column: the source's
     *                                         published runs up to and including `$runId`
     * @return int listings written
     */
    public function handle(int $feedSourceId, int $runId, QueryBuilder $publishedRunIds): int
    {
        $publishedRunsSinceLastSeen = DB::query()
            ->selectRaw(sprintf('CASE WHEN count(*) > %1$d THEN %1$d ELSE count(*) END', self::MAX_MISSING_RUN_COUNT))
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
