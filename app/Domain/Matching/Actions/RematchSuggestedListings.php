<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Contracts\ComplianceHoldCheck;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Audit\AuditActor;
use App\Domain\Platform\Audit\AuditLogger;
use App\Domain\Platform\Features\Feature;
use App\Domain\Platform\Features\FeatureFlags;
use App\Models\MatchingDecision;
use App\Models\MerchantProduct;
use Closure;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Staff operation (BACKLOG F-06): after `matching-auto-publish` is switched
 * back on, re-run matching for the listings that were demoted to `suggested`
 * while it was off — exactly the listings whose CURRENT decision is a
 * `suggested` one with reason {@see MatchListing::REASON_AUTO_PUBLISH_DISABLED}.
 * Ordinary confirm-bucket suggestions are left alone (re-running them would
 * only grow the history).
 *
 * Each listing is re-matched with {@see MatchContext::$forceRematch} (reason
 * `forced`), so the normal rules apply: auto-bucket results are linked, a
 * product blocked in the listing's market is held, rejected pairs are never
 * re-linked. Listings are processed in id chunks (candidates primed per chunk);
 * every listing whose state changed gets one `matching.rematched` audit row
 * (before/after {product_id, match_status}, `operation: rematch_suggested`)
 * in the same transaction as its new decision.
 *
 * Refused while the feature is still off (every listing would be demoted
 * again). A dry run only counts the listings that would be re-matched.
 */
final class RematchSuggestedListings
{
    public const string OPERATION = 'rematch_suggested';

    public const int CHUNK = 200;

    public function __construct(
        private readonly MatchListing $match,
        private readonly AuditLogger $audit,
        private readonly FeatureFlags $features,
    ) {}

    /**
     * @param  Closure(MerchantProduct): ?ComplianceHoldCheck  $holdFor  the compliance check of a listing's market
     *
     * @throws LogicException when `matching-auto-publish` is off and this is not a dry run
     */
    public function handle(
        AuditActor $actor,
        DateTimeImmutable $decidedAt,
        Closure $holdFor,
        ?int $merchantId = null,
        bool $dryRun = false,
        int $chunk = self::CHUNK,
    ): RematchSuggestedSummary {
        if ($dryRun) {
            return new RematchSuggestedSummary(eligible: $this->eligible($merchantId)->count(), dryRun: true);
        }

        if (! $this->features->enabled(Feature::MatchingAutoPublish)) {
            throw new LogicException('Enable the matching-auto-publish feature before re-matching demoted listings.');
        }

        $counts = ['eligible' => 0, 'linked' => 0, 'held' => 0, 'suggested' => 0, 'unmatched' => 0];

        $this->eligible($merchantId)->chunkById(max(1, $chunk), function (Collection $listings) use ($actor, $decidedAt, $holdFor, &$counts): void {
            $this->match->prime(array_values($listings->all()));

            foreach ($listings as $listing) {
                /** @var MerchantProduct $listing */
                $status = $this->rematch($listing, $actor, $decidedAt, $holdFor($listing));
                $counts['eligible']++;
                $counts[self::bucket($status)]++;
            }
        });

        return new RematchSuggestedSummary(
            eligible: $counts['eligible'],
            dryRun: false,
            linked: $counts['linked'],
            held: $counts['held'],
            suggested: $counts['suggested'],
            unmatched: $counts['unmatched'],
        );
    }

    private function rematch(MerchantProduct $listing, AuditActor $actor, DateTimeImmutable $decidedAt, ?ComplianceHoldCheck $hold): ListingMatchStatus
    {
        return DB::transaction(function () use ($listing, $actor, $decidedAt, $hold): ListingMatchStatus {
            $before = ManualLink::auditState($listing);
            $outcome = $this->match->handle($listing, new MatchContext(null, $hold, $decidedAt, forceRematch: true));
            $after = MerchantProduct::query()->findOrFail($listing->id);

            if (ManualLink::auditState($after) !== $before) {
                $this->audit->record(AuditAction::MatchingRematched, $actor, $after, $before, [
                    ...ManualLink::auditState($after),
                    'operation' => self::OPERATION,
                    'decision_id' => $outcome->decisionId,
                ]);
            }

            return $outcome->status;
        });
    }

    /**
     * @return Builder<MerchantProduct>
     */
    private function eligible(?int $merchantId): Builder
    {
        return MerchantProduct::query()
            ->where('match_status', ListingMatchStatus::Suggested->value)
            ->when($merchantId !== null, fn (Builder $query) => $query->where('merchant_id', $merchantId))
            ->whereExists(fn ($query) => $query->selectRaw('1')
                ->from((new MatchingDecision)->getTable())
                ->whereColumn('matching_decisions.id', 'merchant_products.current_matching_decision_id')
                ->where('matching_decisions.reason', MatchListing::REASON_AUTO_PUBLISH_DISABLED));
    }

    private static function bucket(ListingMatchStatus $status): string
    {
        return match ($status) {
            ListingMatchStatus::Auto, ListingMatchStatus::Manual => 'linked',
            ListingMatchStatus::ComplianceHold => 'held',
            ListingMatchStatus::Suggested => 'suggested',
            ListingMatchStatus::Unmatched, ListingMatchStatus::Rejected => 'unmatched',
        };
    }
}
