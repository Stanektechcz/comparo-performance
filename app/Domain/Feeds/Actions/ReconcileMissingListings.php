<?php

namespace App\Domain\Feeds\Actions;

use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedErrorSeverity;
use App\Domain\Feeds\FeedRunOutcome;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Offers\Actions\DeactivateOffer;
use App\Domain\Offers\ListingStatus;
use App\Domain\Offers\OfferDeactivationReason;
use App\Models\FeedError;
use App\Models\FeedRun;
use App\Models\MerchantProduct;
use App\Models\Offer;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reconciliation of a publishing run (§7, D-25, A-10). Idempotent, so a
 * retried publish stage never double counts:
 *
 * 1. Listings owned by the source and not seen in this run become `missing`;
 *    `missing_run_count` is DERIVED (published runs of the source after the
 *    listing's last sighting, up to this one), never incremented.
 * 2. Their active offers are deactivated (`missing_from_feed` after
 *    `missing_runs_before_deactivation` misses, else `not_seen` once the
 *    listing is `unseen_days_before_deactivation` days old). Never deleted;
 *    a reappearing SKU is reactivated by UpsertListing + PublishOffer.
 * 3. Mass-removal guard: when that would hide more than `mass_removal_ratio`
 *    of the source's live offers, nothing is hidden; the run gets one
 *    MASS_REMOVAL_HELD warning (and so finishes `published_with_warnings`).
 *    Sources with fewer than `mass_removal_min_offers` live offers are exempt,
 *    otherwise a one- or two-SKU feed could never drop a product.
 */
final class ReconcileMissingListings
{
    public function __construct(private readonly DeactivateOffer $deactivateOffer) {}

    public function handle(FeedRun $run, DateTimeImmutable $observedAt): ReconcileResult
    {
        $this->markMissing($run);

        $missingRuns = max(1, (int) config('comparo.feeds.missing_runs_before_deactivation', 2));
        $unseenBefore = $observedAt->modify('-'.max(1, (int) config('comparo.feeds.unseen_days_before_deactivation', 7)).' days');
        $candidates = $this->candidates($run, $missingRuns, $unseenBefore);

        if ($candidates === []) {
            return new ReconcileResult(0, false, []);
        }

        $liveOffers = $this->liveOffersOfSource($run->feed_source_id)->count();
        $ratio = (float) config('comparo.feeds.mass_removal_ratio', 0.5);
        $guardFrom = max(1, (int) config('comparo.feeds.mass_removal_min_offers', 10));

        if ($liveOffers >= $guardFrom && count($candidates) > $ratio * $liveOffers) {
            $this->holdMassRemoval($run, count($candidates), $ratio);

            return new ReconcileResult(0, true, []);
        }

        return $this->deactivate($run, $candidates, $observedAt);
    }

    private function markMissing(FeedRun $run): void
    {
        // Correlated subquery: published runs of the source after the listing's last sighting, up to this run.
        $publishedRunsSinceLastSeen = FeedRun::query()
            ->toBase()
            ->selectRaw('count(*)')
            ->where('feed_runs.feed_source_id', $run->feed_source_id)
            ->where('feed_runs.id', '<=', $run->id)
            ->whereRaw('feed_runs.id > COALESCE(merchant_products.last_seen_run_id, 0)')
            ->where(static fn ($query) => $query
                ->where('feed_runs.status', FeedRunStatus::Publishing->value)
                ->orWhere(static fn ($completed) => $completed
                    ->where('feed_runs.status', FeedRunStatus::Completed->value)
                    ->whereIn('feed_runs.outcome', [FeedRunOutcome::Published->value, FeedRunOutcome::PublishedWithWarnings->value])));

        $this->unseenListings($run)->toBase()->update([
            'status' => ListingStatus::Missing->value,
            'missing_run_count' => $publishedRunsSinceLastSeen,
        ]);
    }

    /**
     * @return Builder<MerchantProduct>
     */
    private function unseenListings(FeedRun $run): Builder
    {
        return MerchantProduct::query()
            ->where('feed_source_id', $run->feed_source_id)
            ->whereIn('status', [ListingStatus::Active->value, ListingStatus::Missing->value])
            ->where(static fn (Builder $query) => $query->whereNull('last_seen_run_id')->orWhere('last_seen_run_id', '!=', $run->id));
    }

    /**
     * Active offers of missing listings past a deactivation threshold.
     *
     * @return list<array{offer: Offer, reason: OfferDeactivationReason}>
     */
    private function candidates(FeedRun $run, int $missingRuns, DateTimeImmutable $unseenBefore): array
    {
        $listings = $this->unseenListings($run)
            ->where('status', ListingStatus::Missing->value)
            ->where(static fn (Builder $query) => $query->where('missing_run_count', '>=', $missingRuns)->orWhere('last_seen_at', '<', $unseenBefore))
            ->get(['id', 'missing_run_count'])
            ->keyBy('id');

        if ($listings->isEmpty()) {
            return [];
        }

        return array_values(Offer::query()
            ->whereIn('merchant_product_id', $listings->keys())
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(static fn (Offer $offer): array => [
                'offer' => $offer,
                'reason' => $listings[$offer->merchant_product_id]->missing_run_count >= $missingRuns
                    ? OfferDeactivationReason::MissingFromFeed
                    : OfferDeactivationReason::NotSeen,
            ])
            ->all());
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
     * @param  list<array{offer: Offer, reason: OfferDeactivationReason}>  $candidates
     */
    private function deactivate(FeedRun $run, array $candidates, DateTimeImmutable $observedAt): ReconcileResult
    {
        $deactivated = 0;
        $productIds = [];

        foreach ($candidates as ['offer' => $offer, 'reason' => $reason]) {
            $done = DB::transaction(function () use ($run, $offer, $reason, $observedAt): bool {
                if (! $this->deactivateOffer->handle($offer, $reason, $observedAt)) {
                    return false;
                }

                FeedRun::query()->whereKey($run->id)->toBase()->update(['offers_deactivated' => DB::raw('offers_deactivated + 1')]);

                return true;
            });

            if ($done) {
                $deactivated++;
                $productIds[$offer->product_id] = true;
            }
        }

        return new ReconcileResult($deactivated, false, array_keys($productIds));
    }
}
