<?php

namespace App\Domain\Feeds\Actions;

use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedErrorSeverity;
use App\Domain\Feeds\FeedRunOutcome;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Offers\Actions\DeactivateOffer;
use App\Domain\Offers\Actions\MarkListingsMissing;
use App\Domain\Offers\ListingStatus;
use App\Domain\Offers\OfferDeactivationReason;
use App\Models\FeedError;
use App\Models\FeedRun;
use App\Models\MerchantProduct;
use App\Models\Offer;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reconciliation of a publishing run (§7, D-25, A-10). Idempotent, so a
 * retried publish stage never double counts:
 *
 * 1. Listings owned by the source and not seen in this run become `missing`
 *    ({@see MarkListingsMissing}: the Offers context owns listing writes);
 *    `missing_run_count` is DERIVED (published runs of the source after the
 *    listing's last sighting, up to this one), never incremented.
 * 2. Their active offers are deactivated (`missing_from_feed` after
 *    `missing_runs_before_deactivation` misses, else `not_seen` once the
 *    listing is `unseen_days_before_deactivation` days old). Never deleted;
 *    a reappearing SKU is reactivated by UpsertListing + PublishOffer.
 * 3. Mass-removal guard: when that would hide more than `mass_removal_ratio`
 *    of the source's live offers (both counted, never loaded), nothing is
 *    hidden; the run gets one MASS_REMOVAL_HELD warning (and so finishes
 *    `published_with_warnings`). Sources with fewer than
 *    `mass_removal_min_offers` live offers are exempt, otherwise a one- or
 *    two-SKU feed could never drop a product.
 *
 * Deactivation walks the candidate offers in id-ordered chunks
 * (`comparo.feeds.pipeline_chunk`), one transaction per chunk, so memory and
 * lock time stay bounded however many listings a source drops.
 */
final class ReconcileMissingListings
{
    public function __construct(
        private readonly DeactivateOffer $deactivateOffer,
        private readonly MarkListingsMissing $markListingsMissing,
    ) {}

    public function handle(FeedRun $run, DateTimeImmutable $observedAt): ReconcileResult
    {
        $this->markListingsMissing->handle($run->feed_source_id, $run->id, $this->publishedRunIds($run));

        $missingRuns = max(1, (int) config('comparo.feeds.missing_runs_before_deactivation', 2));
        $unseenBefore = $observedAt->modify('-'.max(1, (int) config('comparo.feeds.unseen_days_before_deactivation', 7)).' days');
        $candidateCount = $this->candidateOffers($run, $missingRuns, $unseenBefore)->count();

        if ($candidateCount === 0) {
            return new ReconcileResult(0, false, []);
        }

        $liveOffers = $this->liveOffersOfSource($run->feed_source_id)->count();
        $ratio = (float) config('comparo.feeds.mass_removal_ratio', 0.5);
        $guardFrom = max(1, (int) config('comparo.feeds.mass_removal_min_offers', 10));

        if ($liveOffers >= $guardFrom && $candidateCount > $ratio * $liveOffers) {
            $this->holdMassRemoval($run, $candidateCount, $ratio);

            return new ReconcileResult(0, true, []);
        }

        return $this->deactivate($run, $missingRuns, $unseenBefore, $observedAt);
    }

    /**
     * Ids of the source's published runs up to this one: completed as
     * published (with or without warnings), or publishing (this run).
     */
    private function publishedRunIds(FeedRun $run): QueryBuilder
    {
        return FeedRun::query()
            ->toBase()
            ->select('feed_runs.id')
            ->where('feed_runs.feed_source_id', $run->feed_source_id)
            ->where('feed_runs.id', '<=', $run->id)
            ->where(static fn ($query) => $query
                ->where('feed_runs.status', FeedRunStatus::Publishing->value)
                ->orWhere(static fn ($completed) => $completed
                    ->where('feed_runs.status', FeedRunStatus::Completed->value)
                    ->whereIn('feed_runs.outcome', [FeedRunOutcome::Published->value, FeedRunOutcome::PublishedWithWarnings->value])));
    }

    /**
     * Missing listings of the source past a deactivation threshold.
     *
     * @return Builder<MerchantProduct>
     */
    private function candidateListings(FeedRun $run, int $missingRuns, DateTimeImmutable $unseenBefore): Builder
    {
        return MarkListingsMissing::unseenListings($run->feed_source_id, $run->id)
            ->where('status', ListingStatus::Missing->value)
            ->where(static fn (Builder $query) => $query->where('missing_run_count', '>=', $missingRuns)->orWhere('last_seen_at', '<', $unseenBefore));
    }

    /**
     * Active offers of the candidate listings.
     *
     * @return Builder<Offer>
     */
    private function candidateOffers(FeedRun $run, int $missingRuns, DateTimeImmutable $unseenBefore): Builder
    {
        return Offer::query()
            ->where('is_active', true)
            ->whereIn('merchant_product_id', $this->candidateListings($run, $missingRuns, $unseenBefore)->select('id'));
    }

    /**
     * @return Builder<Offer>
     */
    private function liveOffersOfSource(int $sourceId): Builder
    {
        return Offer::query()
            ->where('is_active', true)
            ->whereIn('merchant_product_id', MerchantProduct::query()->select('id')->where('feed_source_id', $sourceId));
    }

    private function holdMassRemoval(FeedRun $run, int $count, float $ratio): void
    {
        DB::transaction(function () use ($run, $count, $ratio): void {
            $alreadyHeld = FeedError::query()
                ->where('feed_run_id', $run->id)
                ->where('code', FeedErrorCode::MassRemovalHeld->value)
                ->exists();

            if ($alreadyHeld) {
                return;
            }

            FeedError::query()->create([
                'feed_run_id' => $run->id,
                'merchant_id' => $run->merchant_id,
                'code' => FeedErrorCode::MassRemovalHeld->value,
                'severity' => FeedErrorSeverity::Warning,
                'message_params' => ['count' => $count, 'percent' => (int) round($ratio * 100)],
            ]);

            FeedRun::query()->whereKey($run->id)->toBase()->update(['warnings' => DB::raw('warnings + 1')]);
        });
    }

    /**
     * Deactivates the candidate offers chunk by chunk, in id order.
     */
    private function deactivate(FeedRun $run, int $missingRuns, DateTimeImmutable $unseenBefore, DateTimeImmutable $observedAt): ReconcileResult
    {
        $deactivated = 0;
        $productIds = [];

        $this->candidateOffers($run, $missingRuns, $unseenBefore)
            ->chunkById(max(1, (int) config('comparo.feeds.pipeline_chunk', 200)), function (Collection $offers) use ($run, $missingRuns, $observedAt, &$deactivated, &$productIds): void {
                foreach ($this->deactivateChunk($run, $offers, $missingRuns, $observedAt) as $productId) {
                    $deactivated++;
                    $productIds[$productId] = true;
                }
            });

        return new ReconcileResult($deactivated, false, array_keys($productIds));
    }

    /**
     * One transaction per chunk: the chunk's deactivations and its
     * `offers_deactivated` increment commit together, so a retry never
     * double counts.
     *
     * @param  Collection<int, Offer>  $offers
     * @return list<int> product id of every offer this chunk deactivated
     */
    private function deactivateChunk(FeedRun $run, Collection $offers, int $missingRuns, DateTimeImmutable $observedAt): array
    {
        /** @var array<int, int> $missingRunCounts */
        $missingRunCounts = MerchantProduct::query()
            ->whereKey($offers->pluck('merchant_product_id')->all())
            ->pluck('missing_run_count', 'id')
            ->all();

        return DB::transaction(function () use ($run, $offers, $missingRuns, $missingRunCounts, $observedAt): array {
            $productIds = [];

            foreach ($offers as $offer) {
                $reason = (int) ($missingRunCounts[$offer->merchant_product_id] ?? 0) >= $missingRuns
                    ? OfferDeactivationReason::MissingFromFeed
                    : OfferDeactivationReason::NotSeen;

                if ($this->deactivateOffer->handle($offer, $reason, $observedAt)) {
                    $productIds[] = $offer->product_id;
                }
            }

            if ($productIds !== []) {
                FeedRun::query()->whereKey($run->id)->toBase()->increment('offers_deactivated', count($productIds));
            }

            return $productIds;
        });
    }
}
