<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\MatchDecisionKind;
use DateTimeImmutable;

/**
 * One decision to append plus the listing state it produces.
 *
 * `productId` is the decision's product (linked, suggested, held or null);
 * `listingProductId` is what merchant_products.product_id becomes.
 */
final readonly class DecisionDraft
{
    /**
     * @param  array<string, mixed>  $components
     */
    public function __construct(
        public MatchDecisionKind $kind,
        public ?int $productId,
        public ?int $previousProductId,
        public ?int $policyId,
        public ?int $score,
        public array $components,
        public ?string $reason,
        public DateTimeImmutable $decidedAt,
        public ListingMatchStatus $listingStatus,
        public ?int $listingProductId,
        public ?int $listingScore,
        public ?int $feedRunId = null,
        public ?int $decidedByUserId = null,
        public ?string $note = null,
    ) {}
}
