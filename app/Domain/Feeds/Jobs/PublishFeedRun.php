<?php

namespace App\Domain\Feeds\Jobs;

use App\Domain\Feeds\Actions\ReconcileMissingListings;
use App\Domain\Feeds\FeedItemValidationStatus;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\Lifecycle\FeedRunTransitions;
use App\Domain\Feeds\Pipeline\FeedItemListing;
use App\Domain\Feeds\Pipeline\FeedRunPipeline;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Offers\Actions\DeactivateOffer;
use App\Domain\Offers\Actions\PublishContext;
use App\Domain\Offers\Actions\PublishOffer;
use App\Domain\Offers\Actions\PublishOutcome;
use App\Domain\Offers\OfferDeactivationReason;
use App\Domain\Pricing\Actions\RecheckProductAnomalies;
use App\Domain\Pricing\History\SnapshotSource;
use App\Models\FeedItem;
use App\Models\FeedRun;
use App\Models\MerchantProduct;
use App\Models\Offer;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Stage 4: matching → publishing (FinalizeFeedRun then completes the run).
 *
 * Items not published yet (`diff_action` null), id-ordered, one transaction
 * per chunk — the item's diff_action and the run counters commit together with
 * the offers, so a retry resumes after the last committed chunk without
 * duplicates:
 * - listing linked (auto/manual) → {@see PublishOffer} (lock order listing →
 *   offer, snapshot in the same transaction); diff_action = publish outcome;
 * - listing in compliance hold → its active offer is deactivated (`held`);
 * - otherwise nothing is published (`not_linked`).
 * Then {@see ReconcileMissingListings} and {@see RecheckProductAnomalies} for
 * every product the run touched.
 */
final class PublishFeedRun implements ShouldQueue
{
    use Dispatchable, InteractsWithFeedRun, InteractsWithQueue, Queueable;

    public const string DIFF_HELD = 'held';

    public const string DIFF_NOT_LINKED = 'not_linked';

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $runId)
    {
        $this->onQueue(FeedRunPipeline::QUEUE_PRICING);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(
        FeedRunTransitions $transitions,
        PublishOffer $publishOffer,
        DeactivateOffer $deactivateOffer,
        ReconcileMissingListings $reconcile,
        RecheckProductAnomalies $recheckAnomalies,
    ): void {
        $run = $this->loadRun();

        if ($run === null || ! $this->claim($run, $transitions)) {
            return;
        }

        $observedAt = Date::instance($run->fetched_at ?? $run->started_at ?? Date::now())->toDateTimeImmutable();
        $productIds = [];

        FeedItem::query()
            ->where('feed_run_id', $run->id)
            ->whereNull('diff_action')
            ->whereNotNull('merchant_product_id')
            ->whereIn('validation_status', [FeedItemValidationStatus::Valid->value, FeedItemValidationStatus::Warning->value])
            ->chunkById(max(1, (int) config('comparo.feeds.pipeline_chunk', 200)), function (Collection $items) use ($run, $observedAt, $publishOffer, $deactivateOffer, &$productIds): void {
                $productIds = [...$productIds, ...$this->publishChunk($items, $run, $observedAt, $publishOffer, $deactivateOffer)];
            });

        $reconciled = $reconcile->handle($run, $observedAt);

        $recheckAnomalies->handle(array_values(array_unique([...$this->publishedProductIds($run), ...$productIds, ...$reconciled->productIds])));
    }

    private function claim(FeedRun $run, FeedRunTransitions $transitions): bool
    {
        return match ($run->status) {
            FeedRunStatus::Matching => $transitions->advance($run->id, FeedRunStatus::Matching, FeedRunStatus::Publishing),
            FeedRunStatus::Publishing => true,
            default => false,
        };
    }

    /**
     * @param  Collection<int, FeedItem>  $items
     * @return list<int> products whose offers changed
     */
    private function publishChunk(Collection $items, FeedRun $run, DateTimeImmutable $observedAt, PublishOffer $publishOffer, DeactivateOffer $deactivateOffer): array
    {
        return DB::transaction(function () use ($items, $run, $observedAt, $publishOffer, $deactivateOffer): array {
            $listings = MerchantProduct::query()->whereKey($items->pluck('merchant_product_id')->all())->get()->keyBy('id');
            $counters = ['offers_created' => 0, 'offers_updated' => 0, 'offers_unchanged' => 0, 'offers_reactivated' => 0, 'offers_deactivated' => 0, 'price_changes' => 0, 'anomalies' => 0];
            $productIds = [];
            $context = new PublishContext(SnapshotSource::Feed, $run->id, $observedAt);

            foreach ($items as $item) {
                $listing = $listings->get($item->merchant_product_id);
                $diff = self::DIFF_NOT_LINKED;

                if ($listing !== null && $listing->product_id !== null && $listing->match_status->isLinked()) {
                    $result = $publishOffer->handle($listing, FeedItemListing::terms($item), $context);
                    $diff = $result->outcome->value;
                    $counters[$this->counterFor($result->outcome)]++;
                    $counters['price_changes'] += $result->priceChanged ? 1 : 0;
                    $counters['anomalies'] += $result->anomaly !== null ? 1 : 0;
                    $productIds[] = $listing->product_id;
                } elseif ($listing !== null && $listing->match_status === ListingMatchStatus::ComplianceHold) {
                    $diff = self::DIFF_HELD;
                    $offer = Offer::query()->where('merchant_product_id', $listing->id)->where('is_active', true)->first();

                    if ($offer !== null && $deactivateOffer->handle($offer, OfferDeactivationReason::ComplianceHold, $observedAt)) {
                        $counters['offers_deactivated']++;
                        $productIds[] = $offer->product_id;
                    }
                }

                FeedItem::query()->whereKey($item->id)->toBase()->update(['diff_action' => $diff, 'updated_at' => Date::now()]);
            }

            FeedRun::query()->whereKey($run->id)->toBase()->incrementEach($counters);

            return $productIds;
        });
    }

    private function counterFor(PublishOutcome $outcome): string
    {
        return match ($outcome) {
            PublishOutcome::Created => 'offers_created',
            PublishOutcome::Updated => 'offers_updated',
            PublishOutcome::Unchanged => 'offers_unchanged',
            PublishOutcome::Reactivated => 'offers_reactivated',
        };
    }

    /**
     * Products published by earlier (committed) attempts of this run, so a
     * retry still rechecks them.
     *
     * @return list<int>
     */
    private function publishedProductIds(FeedRun $run): array
    {
        return array_values(MerchantProduct::query()
            ->whereIn('id', FeedItem::query()->select('merchant_product_id')->where('feed_run_id', $run->id)->whereNotNull('diff_action'))
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all());
    }
}
