<?php

namespace App\Domain\Reviews\Abuse;

/**
 * A review tagged with a candidate duplicate cluster (the prototype's
 * `reviewFlags[id].cluster`) and its body.
 */
final readonly class ClusteredReview
{
    public function __construct(
        public int $reviewId,
        public string $cluster,
        public ?string $body,
    ) {}
}
