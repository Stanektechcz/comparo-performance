<?php

namespace App\Domain\Feeds\Listeners;

use App\Domain\Feeds\FeedItemValidationStatus;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\Pipeline\FeedItemListing;
use App\Domain\Matching\Events\ProductMatched;
use App\Domain\Offers\Actions\PublishContext;
use App\Domain\Offers\Actions\PublishOffer;
use App\Domain\Pricing\History\SnapshotSource;
use App\Models\FeedItem;
use App\Models\MatchingDecision;
use App\Models\MerchantProduct;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Date;

/**
 * A listing linked OUTSIDE the pipeline (a merchant confirming a suggestion,
 * staff choosing or rematching) publishes its latest feed observation: the
 * newest valid/warning staged item from a completed run of its source.
 *
 * Links made by a feed run (decision.feed_run_id set) are skipped — that
 * run's publish stage publishes its own, current observation, and publishing
 * an older one here could overwrite it. Nothing happens when the listing is
 * no longer linked or no staged observation is left (items are pruned; the
 * next run publishes then).
 *
 * Queued on `pricing` over the long-running connection, after commit.
 */
final class PublishLatestObservation implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly PublishOffer $publishOffer) {}

    public int $timeout = 60;

    public bool $afterCommit = true;

    public function viaConnection(): string
    {
        return (string) config('comparo.queues.long_running_connection');
    }

    public function viaQueue(): string
    {
        return 'pricing';
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(ProductMatched $event): void
    {
        $decision = MatchingDecision::query()->find($event->decisionId);

        if ($decision === null || $decision->feed_run_id !== null) {
            return;
        }

        $listing = MerchantProduct::query()->find($event->listingId);

        if ($listing === null || $listing->product_id === null || ! $listing->match_status->isLinked()) {
            return;
        }

        $item = $this->latestObservation($listing);

        if ($item === null) {
            return;
        }

        $observedAt = Date::instance($item->run->fetched_at ?? $item->run->started_at ?? Date::now())->toDateTimeImmutable();

        $this->publishOffer->handle($listing, FeedItemListing::terms($item), new PublishContext(SnapshotSource::Feed, $item->feed_run_id, $observedAt));
    }

    private function latestObservation(MerchantProduct $listing): ?FeedItem
    {
        return FeedItem::query()
            ->select('feed_items.*')
            ->join('feed_runs', 'feed_runs.id', '=', 'feed_items.feed_run_id')
            ->where('feed_items.merchant_product_id', $listing->id)
            ->whereIn('feed_items.validation_status', [FeedItemValidationStatus::Valid->value, FeedItemValidationStatus::Warning->value])
            ->where('feed_runs.status', FeedRunStatus::Completed->value)
            ->orderByDesc('feed_runs.id')
            ->with('run')
            ->first();
    }
}
