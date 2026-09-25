<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\CandidateStatus;
use App\Domain\Matching\Contracts\ComplianceHoldCheck;
use App\Domain\Matching\Exceptions\MatchDecisionNotAllowed;
use App\Domain\Matching\MatchDecisionKind;
use App\Domain\Matching\Queries\CandidateProducts;
use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Audit\AuditActor;
use App\Domain\Platform\Audit\AuditLogger;
use App\Models\MerchantProduct;
use App\Models\ProductCandidate;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * Staff resolve an open product candidate (audited as `product_candidate.resolved`):
 *
 * - linkExisting: the proposal is an existing ACTIVE product → status
 *   `merged_existing`, linked_product_id set, and every source listing that
 *   is not already linked gets the same manual decision a {@see DecideMatch}
 *   choice would record (one `matching.decided` audit row per listing);
 *   listings linked in the meantime are left alone;
 * - reject: status `rejected`; listings are unchanged.
 *
 * Creating a new canonical product from a candidate is out of scope (A-15, Phase 8).
 */
final class ResolveProductCandidate
{
    public function __construct(
        private readonly ManualLink $manualLink,
        private readonly CandidateProducts $candidates,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws MatchDecisionNotAllowed
     */
    public function linkExisting(
        ProductCandidate $candidate,
        int $productId,
        MatchingActor $actor,
        DateTimeImmutable $resolvedAt,
        ?ComplianceHoldCheck $complianceHold = null,
        ?string $note = null,
    ): ProductCandidate {
        $actor->assertStaff('resolve product candidates');

        return DB::transaction(function () use ($candidate, $productId, $actor, $resolvedAt, $complianceHold, $note): ProductCandidate {
            $locked = $this->lockOpen($candidate);

            if ($this->candidates->candidate($productId) === null) {
                throw MatchDecisionNotAllowed::inactiveProduct($productId);
            }

            $linked = 0;
            $listings = MerchantProduct::query()
                ->whereIn('id', $locked->sources()->select('merchant_product_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($listings as $listing) {
                if ($listing->match_status->isLinked()) {
                    continue;
                }

                $this->manualLink->link(
                    $listing, $productId, MatchDecisionKind::Manual, DecideMatch::REASON_CHOSEN,
                    $actor, $resolvedAt, AuditAction::MatchingDecided, $complianceHold, $note,
                );
                $linked++;
            }

            return $this->resolve($locked, CandidateStatus::MergedExisting, $productId, $actor, $resolvedAt, $note, $linked);
        });
    }

    /**
     * @throws MatchDecisionNotAllowed
     */
    public function reject(ProductCandidate $candidate, MatchingActor $actor, DateTimeImmutable $resolvedAt, ?string $note = null): ProductCandidate
    {
        $actor->assertStaff('resolve product candidates');

        return DB::transaction(fn (): ProductCandidate => $this->resolve(
            $this->lockOpen($candidate), CandidateStatus::Rejected, null, $actor, $resolvedAt, $note, 0,
        ));
    }

    private function lockOpen(ProductCandidate $candidate): ProductCandidate
    {
        $locked = ProductCandidate::query()->lockForUpdate()->findOrFail($candidate->id);

        if (! $locked->status->isOpen()) {
            throw MatchDecisionNotAllowed::candidateNotOpen($locked->id);
        }

        return $locked;
    }

    private function resolve(
        ProductCandidate $candidate,
        CandidateStatus $status,
        ?int $productId,
        MatchingActor $actor,
        DateTimeImmutable $resolvedAt,
        ?string $note,
        int $linkedListings,
    ): ProductCandidate {
        $before = ['status' => $candidate->status->value, 'linked_product_id' => $candidate->linked_product_id];

        $candidate->fill([
            'status' => $status,
            'linked_product_id' => $productId,
            'reviewed_by_user_id' => $actor->user->id,
            'reviewed_at' => $resolvedAt->setTimezone(new DateTimeZone('UTC')),
            'decision_note' => $note,
        ])->save();

        $this->audit->record(AuditAction::ProductCandidateResolved, AuditActor::user($actor->user), $candidate, $before, [
            'status' => $status->value,
            'linked_product_id' => $productId,
            'linked_listings' => $linkedListings,
        ]);

        return $candidate;
    }
}
