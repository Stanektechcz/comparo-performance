<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Contracts\ComplianceHoldCheck;
use App\Domain\Matching\Engine\ProductMatcher;
use App\Domain\Matching\Exceptions\MatchDecisionNotAllowed;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\MatchDecisionKind;
use App\Domain\Matching\Queries\ActiveMatchingPolicy;
use App\Domain\Matching\Queries\CandidateProducts;
use App\Domain\Matching\Queries\ListingFacts;
use App\Domain\Matching\Queries\MatchComponents;
use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Audit\AuditActor;
use App\Domain\Platform\Audit\AuditLogger;
use App\Models\MatchingDecision;
use App\Models\MerchantProduct;
use DateTimeImmutable;

/**
 * A person links a listing to an ACTIVE canonical product (manual decision,
 * staff rematch, candidate resolved as an existing product).
 *
 * The decision records the engine's evidence for the chosen product under the
 * active policy (score + parts), so reviewers see why the link is plausible.
 * A product blocked by the supplied compliance check is linked as
 * `compliance_hold` instead of `manual` (never published). The audit row is
 * written in the caller's transaction, next to the change.
 *
 * Callers hold the listing row lock and have checked the actor's scope.
 */
final class ManualLink
{
    public function __construct(
        private readonly ActiveMatchingPolicy $policies,
        private readonly CandidateProducts $candidates,
        private readonly ProductMatcher $matcher,
        private readonly DecisionWriter $writer,
        private readonly ComplianceHolds $holds,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws MatchDecisionNotAllowed when the product is not active
     */
    public function link(
        MerchantProduct $listing,
        int $productId,
        MatchDecisionKind $kind,
        string $reason,
        MatchingActor $actor,
        DateTimeImmutable $decidedAt,
        AuditAction $auditAction,
        ?ComplianceHoldCheck $complianceHold = null,
        ?string $note = null,
    ): MatchingDecision {
        $candidate = $this->candidates->candidate($productId) ?? throw MatchDecisionNotAllowed::inactiveProduct($productId);
        $facts = ListingFacts::of($listing);
        $active = $this->policies->current();
        $evidence = $this->matcher->scoreCandidate($facts, $candidate, $this->candidates->aliasSets(), $active->policy);
        $held = $complianceHold?->isBlocked($productId) === true;
        $status = $held ? ListingMatchStatus::ComplianceHold : ListingMatchStatus::Manual;
        $before = self::auditState($listing);

        $decision = $this->writer->write($listing, new DecisionDraft(
            kind: $kind,
            productId: $productId,
            previousProductId: $listing->product_id,
            policyId: $active->id,
            score: $evidence->score,
            components: MatchComponents::build($facts, $evidence),
            reason: $held ? MatchListing::REASON_COMPLIANCE_HOLD : $reason,
            decidedAt: $decidedAt,
            listingStatus: $status,
            listingProductId: $productId,
            listingScore: $evidence->score,
            decidedByUserId: $actor->user->id,
            note: $note,
        ));

        if ($held) {
            $this->holds->hold($listing, $productId, $decidedAt);
        }

        $this->recordAudit($auditAction, $actor, $listing, $before);

        return $decision;
    }

    /**
     * @param  array{product_id: ?int, match_status: string}  $before
     */
    public function recordAudit(AuditAction $action, MatchingActor $actor, MerchantProduct $listing, array $before): void
    {
        $after = MerchantProduct::query()->findOrFail($listing->id);

        $this->audit->record($action, AuditActor::user($actor->user), $after, $before, self::auditState($after));
    }

    /**
     * @return array{product_id: ?int, match_status: string}
     */
    public static function auditState(MerchantProduct $listing): array
    {
        return ['product_id' => $listing->product_id, 'match_status' => $listing->match_status->value];
    }
}
