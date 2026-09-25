<?php

namespace App\Domain\Matching\Queries;

use App\Domain\Matching\MatchDecisionKind;
use App\Models\MatchingDecision;
use DateTimeImmutable;

/**
 * One append-only matching decision, for the decision history views.
 */
final readonly class DecisionHistoryEntry
{
    /**
     * @param  list<array<string, mixed>>  $parts
     */
    public function __construct(
        public int $id,
        public int $listingId,
        public int $merchantId,
        public ?string $merchantSku,
        public MatchDecisionKind $kind,
        public ?ProductSummary $product,
        public ?ProductSummary $previousProduct,
        public ?int $policyId,
        public ?int $score,
        public ?string $reason,
        public ?string $note,
        public ?int $decidedByUserId,
        public ?int $supersedesId,
        public ?int $feedRunId,
        public DateTimeImmutable $decidedAt,
        public array $parts,
    ) {}

    /**
     * Expects `merchantProduct:id,merchant_sku`, `product.brand` and
     * `previousProduct.brand` to be eager-loaded.
     */
    public static function fromModel(MatchingDecision $decision): self
    {
        return new self(
            id: $decision->id,
            listingId: $decision->merchant_product_id,
            merchantId: $decision->merchant_id,
            merchantSku: $decision->relationLoaded('merchantProduct') ? $decision->merchantProduct->merchant_sku : null,
            kind: $decision->kind,
            product: ProductSummary::fromNullable($decision->product),
            previousProduct: ProductSummary::fromNullable($decision->previousProduct),
            policyId: $decision->matching_policy_id,
            score: $decision->score,
            reason: $decision->reason,
            note: $decision->note,
            decidedByUserId: $decision->decided_by_user_id,
            supersedesId: $decision->supersedes_id,
            feedRunId: $decision->feed_run_id,
            decidedAt: $decision->decided_at->toDateTimeImmutable(),
            parts: MatchComponents::storedParts($decision->components),
        );
    }
}
