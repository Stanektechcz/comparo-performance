<?php

namespace App\Domain\Matching\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A merchant listing became linked to a canonical product (automatic match,
 * manual decision or staff rematch), or moved to another product. Listeners
 * (the feed pipeline) may now publish the listing's latest observation.
 * Dispatched only after the decision's transaction commits.
 */
final readonly class ProductMatched implements ShouldDispatchAfterCommit
{
    /**
     * @param  string  $kind  the MatchDecisionKind value of the decision that linked the listing
     */
    public function __construct(
        public int $listingId,
        public int $merchantId,
        public int $productId,
        public ?int $previousProductId,
        public string $kind,
        public int $decisionId,
    ) {}
}
