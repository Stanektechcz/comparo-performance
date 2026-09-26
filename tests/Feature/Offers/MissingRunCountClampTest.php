<?php

use App\Domain\Offers\Actions\MarkListingsMissing;
use App\Domain\Offers\ListingStatus;
use App\Models\FeedRun;
use App\Models\FeedSource;
use App\Models\MerchantProduct;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * F-09: merchant_products.missing_run_count is an unsigned tinyint and a
 * DERIVED count (published runs since the last sighting); it is clamped at
 * the column maximum instead of overflowing on a long-dead listing.
 */
function publishedRunsOf(FeedSource $source): Builder
{
    return DB::table('feed_runs')->select('id')->where('feed_source_id', $source->id);
}

it('clamps the derived missing run count at the column maximum', function () {
    $source = FeedSource::factory()->create();
    $listing = MerchantProduct::factory()->fromFeed($source)->create(['last_seen_run_id' => null]);
    $runs = FeedRun::factory()->count(MarkListingsMissing::MAX_MISSING_RUN_COUNT + 5)->forSource($source)->completed()->create();

    $written = app(MarkListingsMissing::class)->handle($source->id, $runs->last()->id, publishedRunsOf($source));

    expect($written)->toBe(1)
        ->and(MarkListingsMissing::MAX_MISSING_RUN_COUNT)->toBe(255)
        ->and($listing->refresh()->missing_run_count)->toBe(255)
        ->and($listing->status)->toBe(ListingStatus::Missing);
});

it('keeps the exact count below the maximum', function () {
    $source = FeedSource::factory()->create();
    $seenIn = FeedRun::factory()->forSource($source)->completed()->create();
    $listing = MerchantProduct::factory()->fromFeed($source)->create(['last_seen_run_id' => $seenIn->id]);
    $runs = FeedRun::factory()->count(3)->forSource($source)->completed()->create();

    app(MarkListingsMissing::class)->handle($source->id, $runs->last()->id, publishedRunsOf($source));

    expect($listing->refresh()->missing_run_count)->toBe(3);
});
