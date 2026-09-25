<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Contracts\ComplianceHoldCheck;
use App\Domain\Matching\Exceptions\MatchDecisionNotAllowed;
use App\Domain\Matching\MatchDecisionKind;
use App\Domain\Offers\Events\OfferRelinked;
use App\Domain\Platform\Audit\AuditAction;
use App\Models\MatchingDecision;
use App\Models\MerchantProduct;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Staff correction: relink a LINKED listing (auto or manual) to another
 * active product. Appends a `rematch` decision superseding the current one
 * (earlier decisions are never touched), moves the offer to the new product
 * ({@see OfferRelinked} → both products' caches are bumped) and audits
 * `matching.rematched`.
 *
 * Price history is NOT rewritten: snapshots recorded before the rematch stay
 * attributed to the old product (price_snapshots is append-only). Which
 * product a snapshot "really" belonged to is derived from the decision
 * history (docs/architecture/phase-2-feeds-matching.md §10).
 */
final class Rematch
{
    public const string REASON = 'staff_rematch';

    public function __construct(private readonly ManualLink $manualLink) {}

    /**
     * @throws MatchDecisionNotAllowed
     */
    public function handle(
        MerchantProduct $listing,
        int $productId,
        MatchingActor $actor,
        DateTimeImmutable $decidedAt,
        ?ComplianceHoldCheck $complianceHold = null,
        ?string $note = null,
    ): MatchingDecision {
        $actor->assertStaff('rematch a listing');

        return DB::transaction(function () use ($listing, $productId, $actor, $decidedAt, $complianceHold, $note): MatchingDecision {
            $locked = MerchantProduct::query()->lockForUpdate()->findOrFail($listing->id);

            if (! $locked->match_status->isLinked() || $locked->product_id === null) {
                throw MatchDecisionNotAllowed::notLinked($locked->id);
            }

            if ($locked->product_id === $productId) {
                throw MatchDecisionNotAllowed::sameProduct($locked->id, $productId);
            }

            return $this->manualLink->link(
                $locked, $productId, MatchDecisionKind::Rematch, self::REASON,
                $actor, $decidedAt, AuditAction::MatchingRematched, $complianceHold, $note,
            );
        });
    }
}
