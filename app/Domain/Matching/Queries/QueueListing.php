<?php

namespace App\Domain\Matching\Queries;

use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\MatchDecisionKind;
use App\Models\MatchingDecision;
use App\Models\MerchantProduct;
use DateTimeImmutable;

/**
 * One listing in a matching review queue: its facts, match state, the product
 * it is linked to / suggested for / held on, and the current decision's evidence.
 */
final readonly class QueueListing
{
    /**
     * @param  list<array<string, mixed>>  $parts  {signal, points, label, params} of the current decision
     */
    public function __construct(
        public int $listingId,
        public int $merchantId,
        public string $merchantSku,
        public ?string $title,
        public ?string $ean,
        public ?string $brandRaw,
        public ?string $packRaw,
        public ?string $variantRaw,
        public ListingMatchStatus $status,
        public ?int $score,
        public ?int $linkedProductId,
        public ?ProductSummary $product,
        public ?int $decisionId,
        public ?MatchDecisionKind $decisionKind,
        public ?string $decisionReason,
        public ?DateTimeImmutable $decidedAt,
        public array $parts,
        public ?DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Expects `product.brand` and `currentDecision.product.brand` to be eager-loaded.
     */
    public static function fromModel(MerchantProduct $listing): self
    {
        /** @var MatchingDecision|null $decision */
        $decision = $listing->currentDecision;
        $product = $listing->product ?? $decision?->product;

        return new self(
            listingId: $listing->id,
            merchantId: $listing->merchant_id,
            merchantSku: $listing->merchant_sku,
            title: $listing->title,
            ean: $listing->ean,
            brandRaw: $listing->brand_raw,
            packRaw: $listing->pack_raw,
            variantRaw: $listing->variant_raw,
            status: $listing->match_status,
            score: $listing->match_score,
            linkedProductId: $listing->product_id,
            product: ProductSummary::fromNullable($product),
            decisionId: $decision?->id,
            decisionKind: $decision?->kind,
            decisionReason: $decision?->reason,
            decidedAt: $decision?->decided_at->toDateTimeImmutable(),
            parts: MatchComponents::storedParts($decision?->components),
            updatedAt: $listing->updated_at?->toDateTimeImmutable(),
        );
    }
}
