<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Engine\MatchBucket;
use App\Domain\Matching\ListingMatchStatus;

/**
 * The result of matching one listing.
 *
 * - `productId`: the linked product (auto/manual), the suggested product
 *   (suggested) or the held product (compliance_hold); null when unmatched.
 * - `bucket`: the engine bucket (null when reused from a decision that did not
 *   store one). With auto-publish off, an `auto` bucket yields status `suggested`.
 * - `reused`: the current decision was kept — nothing was written.
 * - `decisionId`: the listing's current decision after the call (null if none).
 */
final readonly class MatchOutcome
{
    public function __construct(
        public ListingMatchStatus $status,
        public ?int $productId,
        public ?int $score,
        public ?MatchBucket $bucket,
        public bool $reused,
        public ?int $decisionId,
    ) {}
}
