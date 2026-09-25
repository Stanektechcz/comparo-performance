<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Engine\FeedItemFacts;
use App\Domain\Matching\Engine\MatchBucket;
use App\Domain\Matching\Engine\MatchResult;
use App\Domain\Matching\Engine\ProductMatcher;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\MatchDecisionKind;
use App\Domain\Matching\Queries\ActiveMatchingPolicy;
use App\Domain\Matching\Queries\ActivePolicy;
use App\Domain\Matching\Queries\CandidateProducts;
use App\Domain\Matching\Queries\ListingFacts;
use App\Domain\Matching\Queries\MatchComponents;
use App\Domain\Offers\Actions\LinkListing;
use App\Domain\Platform\Features\Feature;
use App\Domain\Platform\Features\FeatureFlags;
use App\Models\MatchingDecision;
use App\Models\MerchantProduct;
use Illuminate\Support\Facades\DB;

/**
 * Matches one merchant listing against the canonical catalogue under the
 * active policy and records the outcome (docs/architecture/phase-2-feeds-matching.md §5).
 *
 * Reuse (no write) unless `forceRematch`:
 * - the current decision is a human one (manual, rejected, rematch) and the
 *   listing's facts are unchanged since it — human decisions are sticky;
 * - the current decision is automatic (auto, suggested, unlinked) under the
 *   ACTIVE policy with unchanged facts — unless the compliance check now
 *   blocks the linked product, or releases a held one.
 *
 * Otherwise the engine runs over the narrowed candidates:
 * - `auto` bucket: blocked product → `compliance_hold` (product kept, offer
 *   deactivated, one open staff conflict); feature `matching-auto-publish`
 *   off → treated as `suggested`; else linked `auto`;
 * - `confirm` bucket: `suggested`, NOT linked (an earlier link is removed,
 *   which deactivates its offer); the decision carries the suggested product;
 * - `unmatched`: unlinked; a decision (`unlinked`) is appended only when the
 *   listing was associated with a product (linked, suggested or held), so
 *   never-matched listings do not grow the history on every run.
 *
 * Everything is written in one transaction; {@see ProductMatched} fires after
 * commit when the listing becomes linked.
 */
final class MatchListing
{
    public const string REASON_COMPLIANCE_HOLD = 'compliance_hold';

    public const string REASON_AUTO_PUBLISH_DISABLED = 'auto_publish_disabled';

    public const string REASON_FORCED = 'forced';

    public const string REASON_POLICY_CHANGED = 'policy_changed';

    public const string REASON_FACTS_CHANGED = 'facts_changed';

    public const string REASON_COMPLIANCE_CHANGED = 'compliance_changed';

    public const string REASON_BELOW_THRESHOLD = 'below_threshold';

    public const string REASON_INITIAL = 'initial';

    public function __construct(
        private readonly ActiveMatchingPolicy $policies,
        private readonly CandidateProducts $candidates,
        private readonly ProductMatcher $matcher,
        private readonly FeatureFlags $features,
        private readonly DecisionWriter $writer,
        private readonly ComplianceHolds $holds,
        private readonly LinkListing $linkListing,
    ) {}

    /**
     * Prefetch the narrowed candidates of a chunk of listings about to be
     * matched with this instance (a handful of queries for the whole chunk
     * instead of one or two per listing). Optional: results are identical
     * without it.
     *
     * @param  list<MerchantProduct>  $listings
     */
    public function prime(array $listings): void
    {
        $this->candidates->prime(array_map(ListingFacts::of(...), $listings));
    }

    public function handle(MerchantProduct $listing, MatchContext $context): MatchOutcome
    {
        $facts = ListingFacts::of($listing);
        $fingerprint = ListingFacts::fingerprint($facts);
        $active = $this->policies->current();
        $current = $this->currentDecision($listing);

        if (! $context->forceRematch && $current !== null && $this->isReusable($listing, $current, $fingerprint, $active, $context)) {
            return $this->reusedOutcome($listing, $current);
        }

        $result = $this->matcher->match($facts, $this->candidates->for($facts), $this->candidates->aliasSets(), $active->policy);
        $reason = $this->reason($current, $fingerprint, $active, $context);

        return DB::transaction(function () use ($listing, $facts, $result, $active, $context, $reason): MatchOutcome {
            $locked = MerchantProduct::query()->lockForUpdate()->findOrFail($listing->id);

            return $this->apply($locked, $facts, $result, $active, $context, $reason);
        });
    }

    private function apply(MerchantProduct $listing, FeedItemFacts $facts, MatchResult $result, ActivePolicy $active, MatchContext $context, string $reason): MatchOutcome
    {
        $productId = $result->bestProductId;
        $components = MatchComponents::build($facts, $result);

        if ($productId === null || $result->bucket === MatchBucket::Unmatched) {
            return $this->applyUnmatched($listing, $result, $components, $active, $context);
        }

        $status = ListingMatchStatus::Suggested;
        $kind = MatchDecisionKind::Suggested;
        $held = false;

        if ($result->bucket === MatchBucket::Auto) {
            $kind = MatchDecisionKind::Auto;

            if ($context->complianceHold?->isBlocked($productId) === true) {
                [$status, $reason, $held] = [ListingMatchStatus::ComplianceHold, self::REASON_COMPLIANCE_HOLD, true];
            } elseif (! $this->features->enabled(Feature::MatchingAutoPublish)) {
                [$status, $kind, $reason] = [ListingMatchStatus::Suggested, MatchDecisionKind::Suggested, self::REASON_AUTO_PUBLISH_DISABLED];
            } else {
                $status = ListingMatchStatus::Auto;
            }
        }

        $decision = $this->writer->write($listing, new DecisionDraft(
            kind: $kind,
            productId: $productId,
            previousProductId: $listing->product_id,
            policyId: $active->id,
            score: $result->score,
            components: $components,
            reason: $reason,
            decidedAt: $context->decidedAt,
            listingStatus: $status,
            listingProductId: $status === ListingMatchStatus::Suggested ? null : $productId,
            listingScore: $result->score,
            feedRunId: $context->feedRunId,
        ));

        if ($held) {
            $this->holds->hold($listing, $productId, $context->decidedAt);
        }

        return new MatchOutcome($status, $productId, $result->score, $result->bucket, false, $decision->id);
    }

    /**
     * @param  array<string, mixed>  $components
     */
    private function applyUnmatched(MerchantProduct $listing, MatchResult $result, array $components, ActivePolicy $active, MatchContext $context): MatchOutcome
    {
        $current = $this->currentDecision($listing);
        $associatedProductId = $listing->product_id ?? $current?->product_id;

        if ($associatedProductId !== null) {
            $decision = $this->writer->write($listing, new DecisionDraft(
                kind: MatchDecisionKind::Unlinked,
                productId: null,
                previousProductId: $associatedProductId,
                policyId: $active->id,
                score: $result->score,
                components: $components,
                reason: self::REASON_BELOW_THRESHOLD,
                decidedAt: $context->decidedAt,
                listingStatus: ListingMatchStatus::Unmatched,
                listingProductId: null,
                listingScore: $result->score,
                feedRunId: $context->feedRunId,
            ));

            return new MatchOutcome(ListingMatchStatus::Unmatched, null, $result->score, MatchBucket::Unmatched, false, $decision->id);
        }

        if ($listing->match_status !== ListingMatchStatus::Unmatched || $listing->match_score !== $result->score) {
            $this->linkListing->handle($listing, null, ListingMatchStatus::Unmatched, $result->score, $context->decidedAt);
        }

        return new MatchOutcome(ListingMatchStatus::Unmatched, null, $result->score, MatchBucket::Unmatched, false, $current?->id);
    }

    private function isReusable(MerchantProduct $listing, MatchingDecision $current, string $fingerprint, ActivePolicy $active, MatchContext $context): bool
    {
        if (MatchComponents::storedFingerprint($current->components) !== $fingerprint) {
            return false;
        }

        if (in_array($current->kind, [MatchDecisionKind::Manual, MatchDecisionKind::Rejected, MatchDecisionKind::Rematch], true)) {
            return true;
        }

        if ($current->matching_policy_id !== $active->id) {
            return false;
        }

        return ! $this->complianceChanged($listing, $context);
    }

    /**
     * A linked product that is now blocked, or a held product that is now
     * released, must be re-evaluated even when facts and policy are unchanged.
     */
    private function complianceChanged(MerchantProduct $listing, MatchContext $context): bool
    {
        if ($context->complianceHold === null || $listing->product_id === null) {
            return false;
        }

        $blocked = $context->complianceHold->isBlocked($listing->product_id);

        return match ($listing->match_status) {
            ListingMatchStatus::Auto => $blocked,
            ListingMatchStatus::ComplianceHold => ! $blocked,
            default => false,
        };
    }

    private function reason(?MatchingDecision $current, string $fingerprint, ActivePolicy $active, MatchContext $context): string
    {
        return match (true) {
            $context->forceRematch => self::REASON_FORCED,
            $current !== null && MatchComponents::storedFingerprint($current->components) !== $fingerprint => self::REASON_FACTS_CHANGED,
            $current !== null && $current->matching_policy_id !== $active->id => self::REASON_POLICY_CHANGED,
            $current !== null => self::REASON_COMPLIANCE_CHANGED,
            default => self::REASON_INITIAL,
        };
    }

    private function reusedOutcome(MerchantProduct $listing, MatchingDecision $current): MatchOutcome
    {
        $bucket = MatchComponents::storedBucket($current->components);

        return new MatchOutcome(
            status: $listing->match_status,
            productId: $listing->product_id ?? $current->product_id,
            score: $listing->match_score,
            bucket: $bucket === null ? null : MatchBucket::tryFrom($bucket),
            reused: true,
            decisionId: $current->id,
        );
    }

    private function currentDecision(MerchantProduct $listing): ?MatchingDecision
    {
        if ($listing->current_matching_decision_id === null) {
            return null;
        }

        return MatchingDecision::query()->find($listing->current_matching_decision_id);
    }
}
