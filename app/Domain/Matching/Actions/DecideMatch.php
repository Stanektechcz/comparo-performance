<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Contracts\ComplianceHoldCheck;
use App\Domain\Matching\Exceptions\ListingOutsideMerchantScope;
use App\Domain\Matching\Exceptions\MatchDecisionNotAllowed;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\MatchDecisionKind;
use App\Domain\Matching\Queries\ActiveMatchingPolicy;
use App\Domain\Matching\Queries\ListingFacts;
use App\Domain\Matching\Queries\MatchComponents;
use App\Domain\Platform\Audit\AuditAction;
use App\Models\MatchingDecision;
use App\Models\MerchantProduct;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A merchant's or staff member's decision on a listing (audited as
 * `matching.decided`, before/after {product_id, match_status}):
 *
 * - confirm: link the suggested product (the current `suggested` decision's product);
 * - choose:  link another ACTIVE product — only while the listing is not linked
 *            to a different product (relinking a linked listing is a staff {@see Rematch});
 * - reject:  drop the linked/suggested/held product; the listing becomes
 *            `unmatched` (back in the review queue), its offer is deactivated,
 *            and the `rejected` decision keeps the product in previous_product_id.
 *
 * Manual and rejected decisions are sticky: {@see MatchListing} reuses them
 * until the listing's facts change. Merchant actors may only act on their own
 * merchant's listings ({@see ListingOutsideMerchantScope}).
 */
final class DecideMatch
{
    public const string REASON_CONFIRMED = 'confirmed';

    public const string REASON_CHOSEN = 'chosen';

    public const string REASON_REJECTED = 'rejected';

    public function __construct(
        private readonly ManualLink $manualLink,
        private readonly DecisionWriter $writer,
        private readonly ActiveMatchingPolicy $policies,
    ) {}

    /**
     * @throws ListingOutsideMerchantScope
     * @throws MatchDecisionNotAllowed
     */
    public function confirm(
        MerchantProduct $listing,
        MatchingActor $actor,
        DateTimeImmutable $decidedAt,
        ?ComplianceHoldCheck $complianceHold = null,
        ?string $note = null,
    ): MatchingDecision {
        return DB::transaction(function () use ($listing, $actor, $decidedAt, $complianceHold, $note): MatchingDecision {
            $locked = $this->lock($listing, $actor);
            $current = $this->currentDecision($locked);

            if ($locked->match_status !== ListingMatchStatus::Suggested
                || $current?->kind !== MatchDecisionKind::Suggested
                || $current->product_id === null) {
                throw MatchDecisionNotAllowed::nothingToConfirm($locked->id);
            }

            return $this->manualLink->link(
                $locked, $current->product_id, MatchDecisionKind::Manual, self::REASON_CONFIRMED,
                $actor, $decidedAt, AuditAction::MatchingDecided, $complianceHold, $note,
            );
        });
    }

    /**
     * @throws ListingOutsideMerchantScope
     * @throws MatchDecisionNotAllowed
     */
    public function choose(
        MerchantProduct $listing,
        int $productId,
        MatchingActor $actor,
        DateTimeImmutable $decidedAt,
        ?ComplianceHoldCheck $complianceHold = null,
        ?string $note = null,
    ): MatchingDecision {
        return DB::transaction(function () use ($listing, $productId, $actor, $decidedAt, $complianceHold, $note): MatchingDecision {
            $locked = $this->lock($listing, $actor);

            if ($locked->match_status->isLinked() && $locked->product_id !== $productId) {
                throw MatchDecisionNotAllowed::alreadyLinked($locked->id);
            }

            return $this->manualLink->link(
                $locked, $productId, MatchDecisionKind::Manual, self::REASON_CHOSEN,
                $actor, $decidedAt, AuditAction::MatchingDecided, $complianceHold, $note,
            );
        });
    }

    /**
     * @throws ListingOutsideMerchantScope
     * @throws MatchDecisionNotAllowed
     */
    public function reject(
        MerchantProduct $listing,
        MatchingActor $actor,
        DateTimeImmutable $decidedAt,
        ?string $note = null,
    ): MatchingDecision {
        return DB::transaction(function () use ($listing, $actor, $decidedAt, $note): MatchingDecision {
            $locked = $this->lock($listing, $actor);
            $rejectedProductId = $locked->product_id ?? $this->currentDecision($locked)?->product_id;
            $rejectable = in_array($locked->match_status, [
                ListingMatchStatus::Suggested, ListingMatchStatus::Auto, ListingMatchStatus::Manual, ListingMatchStatus::ComplianceHold,
            ], true);

            if (! $rejectable || $rejectedProductId === null) {
                throw MatchDecisionNotAllowed::nothingToReject($locked->id);
            }

            $before = ManualLink::auditState($locked);
            $facts = ListingFacts::of($locked);

            $decision = $this->writer->write($locked, new DecisionDraft(
                kind: MatchDecisionKind::Rejected,
                productId: null,
                previousProductId: $rejectedProductId,
                policyId: $this->policies->current()->id,
                score: null,
                components: MatchComponents::build($facts, null),
                reason: self::REASON_REJECTED,
                decidedAt: $decidedAt,
                listingStatus: ListingMatchStatus::Unmatched,
                listingProductId: null,
                listingScore: null,
                decidedByUserId: $actor->user->id,
                note: $note,
            ));

            $this->manualLink->recordAudit(AuditAction::MatchingDecided, $actor, $locked, $before);

            return $decision;
        });
    }

    private function lock(MerchantProduct $listing, MatchingActor $actor): MerchantProduct
    {
        $locked = MerchantProduct::query()->lockForUpdate()->findOrFail($listing->id);
        $actor->assertCanActOn($locked);

        return $locked;
    }

    private function currentDecision(MerchantProduct $listing): ?MatchingDecision
    {
        return $listing->current_matching_decision_id === null
            ? null
            : MatchingDecision::query()->find($listing->current_matching_decision_id);
    }
}
