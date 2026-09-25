<?php

namespace App\Domain\Feeds\Jobs;

use App\Domain\Compliance\Queries\ListingMarkets;
use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedErrorSeverity;
use App\Domain\Feeds\FeedItemMatchStatus;
use App\Domain\Feeds\FeedItemValidationStatus;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\Lifecycle\FeedRunTransitions;
use App\Domain\Feeds\Pipeline\FeedItemListing;
use App\Domain\Feeds\Pipeline\FeedRunPipeline;
use App\Domain\Matching\Actions\MatchContext;
use App\Domain\Matching\Actions\MatchListing;
use App\Domain\Matching\Actions\MatchOutcome;
use App\Domain\Matching\Contracts\ComplianceHoldCheck;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Offers\Actions\UpsertListing;
use App\Domain\Offers\Exceptions\SkuOwnedByOtherSource;
use App\Models\FeedError;
use App\Models\FeedItem;
use App\Models\FeedRun;
use App\Models\FeedSource;
use App\Models\MerchantProduct;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Stage 3: normalizing → matching.
 *
 * For every publishable staged item not processed yet (match_status
 * `pending`, id order — a retry resumes where the last attempt stopped):
 * {@see UpsertListing} writes the listing (a SKU owned by another source
 * rejects the row with SKU_OWNED_BY_OTHER_SOURCE), then {@see MatchListing}
 * matches it for the feed's market (compliance hold = products blocked there).
 * The item records the listing, the match status and score. The match metrics
 * are recomputed from the items at the end, so retries never double count.
 * Per chunk, every listing is upserted first, then the chunk's candidates are
 * prefetched at once ({@see MatchListing::prime()}) and the listings matched,
 * so candidate lookups cost a few queries per chunk, not per item.
 * The next stage (PublishFeedRun) moves the run to `publishing`.
 */
final class MatchFeedItems implements ShouldQueue
{
    use Dispatchable, InteractsWithFeedRun, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $runId)
    {
        $this->onQueue(FeedRunPipeline::QUEUE_MATCHING);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * One MatchListing (and so one CandidateProducts brand index) per job run.
     */
    public function handle(FeedRunTransitions $transitions, UpsertListing $upsert, MatchListing $match, ListingMarkets $markets): void
    {
        $run = $this->loadRun();

        if ($run === null || ! $this->claim($run, $transitions)) {
            return;
        }

        $source = FeedSource::query()->with('country.currency')->findOrFail($run->feed_source_id);
        $hold = $markets->holdForMarket(ListingMarkets::market($source->country));
        $observedAt = Date::instance($run->fetched_at ?? $run->started_at ?? Date::now())->toDateTimeImmutable();

        FeedItem::query()
            ->where('feed_run_id', $run->id)
            ->where('match_status', FeedItemMatchStatus::Pending->value)
            ->whereIn('validation_status', [FeedItemValidationStatus::Valid->value, FeedItemValidationStatus::Warning->value])
            ->chunkById(max(1, (int) config('comparo.feeds.pipeline_chunk', 200)), function (Collection $items) use ($run, $upsert, $match, $hold, $observedAt): void {
                $this->processChunk($items, $run, $upsert, $match, $hold, $observedAt);
            });

        FeedRun::query()->whereKey($run->id)->where('status', FeedRunStatus::Matching->value)->toBase()->update($this->metrics($run->id));
    }

    private function claim(FeedRun $run, FeedRunTransitions $transitions): bool
    {
        return match ($run->status) {
            FeedRunStatus::Normalizing => $transitions->advance($run->id, FeedRunStatus::Normalizing, FeedRunStatus::Matching),
            FeedRunStatus::Matching => true,
            default => false,
        };
    }

    /**
     * @param  Collection<int, FeedItem>  $items
     */
    private function processChunk(Collection $items, FeedRun $run, UpsertListing $upsert, MatchListing $match, ComplianceHoldCheck $hold, DateTimeImmutable $observedAt): void
    {
        /** @var array<int, int> $listingIds feed item id => listing id */
        $listingIds = [];

        foreach ($items as $item) {
            try {
                $listingIds[$item->id] = $upsert->handle(FeedItemListing::observation($item, $run, $observedAt))->listing->id;
            } catch (SkuOwnedByOtherSource) {
                $this->rejectForeignSku($item);
            }
        }

        $listings = MerchantProduct::query()->whereKey(array_values($listingIds))->get()->keyBy('id');
        $match->prime(array_values($listings->all()));
        $context = new MatchContext($run->id, $hold, $observedAt);

        foreach ($items as $item) {
            $listing = isset($listingIds[$item->id]) ? $listings->get($listingIds[$item->id]) : null;

            if ($listing !== null) {
                $this->match($item, $listing, $match, $context);
            }
        }
    }

    private function match(FeedItem $item, MerchantProduct $listing, MatchListing $match, MatchContext $context): void
    {
        $outcome = $match->handle($listing, $context);

        $item->forceFill([
            'merchant_product_id' => $listing->id,
            'match_status' => $this->itemStatus($outcome),
            'match_score' => $outcome->score,
            'suggested_product_id' => $outcome->status === ListingMatchStatus::Suggested ? $outcome->productId : null,
        ])->save();
    }

    private function itemStatus(MatchOutcome $outcome): FeedItemMatchStatus
    {
        if ($outcome->reused) {
            return FeedItemMatchStatus::Reused;
        }

        return match ($outcome->status) {
            ListingMatchStatus::Auto, ListingMatchStatus::Manual => FeedItemMatchStatus::Auto,
            ListingMatchStatus::Suggested => FeedItemMatchStatus::Suggested,
            ListingMatchStatus::ComplianceHold => FeedItemMatchStatus::ComplianceHold,
            ListingMatchStatus::Unmatched, ListingMatchStatus::Rejected => FeedItemMatchStatus::Unmatched,
        };
    }

    /**
     * A-14: one owning source per merchant SKU. The row is rejected, with its
     * error and metrics, in one transaction (so a retry never counts it twice).
     */
    private function rejectForeignSku(FeedItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $item->forceFill([
                'validation_status' => FeedItemValidationStatus::Invalid,
                'match_status' => FeedItemMatchStatus::Skipped,
            ])->save();

            FeedError::query()->create([
                'feed_run_id' => $item->feed_run_id,
                'merchant_id' => $item->merchant_id,
                'feed_item_id' => $item->id,
                'row_number' => null,
                'code' => FeedErrorCode::SkuOwnedByOtherSource->value,
                'severity' => FeedErrorSeverity::Error,
                'field' => 'merchant_sku',
                'message_params' => ['sku' => (string) $item->merchant_sku],
            ]);

            FeedRun::query()->whereKey($item->feed_run_id)->toBase()->update([
                'rows_valid' => DB::raw('rows_valid - 1'),
                'rows_invalid' => DB::raw('rows_invalid + 1'),
                'errors' => DB::raw('errors + 1'),
            ]);
        });
    }

    /**
     * Where the run's listings stand after matching (linked = auto or manual).
     *
     * @return array{rows_matched: int, rows_suggested: int, rows_unmatched: int, rows_compliance_hold: int}
     */
    private function metrics(int $runId): array
    {
        $counts = FeedItem::query()
            ->join('merchant_products', 'merchant_products.id', '=', 'feed_items.merchant_product_id')
            ->where('feed_items.feed_run_id', $runId)
            ->whereIn('feed_items.validation_status', [FeedItemValidationStatus::Valid->value, FeedItemValidationStatus::Warning->value])
            ->groupBy('merchant_products.match_status')
            ->selectRaw('merchant_products.match_status as status, count(*) as total')
            ->toBase()
            ->pluck('total', 'status');

        $count = static fn (ListingMatchStatus ...$statuses): int => array_sum(array_map(
            static fn (ListingMatchStatus $status): int => (int) ($counts[$status->value] ?? 0),
            $statuses,
        ));

        return [
            'rows_matched' => $count(ListingMatchStatus::Auto, ListingMatchStatus::Manual),
            'rows_suggested' => $count(ListingMatchStatus::Suggested),
            'rows_unmatched' => $count(ListingMatchStatus::Unmatched, ListingMatchStatus::Rejected),
            'rows_compliance_hold' => $count(ListingMatchStatus::ComplianceHold),
        ];
    }
}
