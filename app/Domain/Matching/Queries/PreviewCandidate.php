<?php

namespace App\Domain\Matching\Queries;

use App\Domain\Matching\Engine\MatchBucket;
use App\Domain\Matching\Engine\MatchLevel;

/**
 * One live-scored candidate product for the side-by-side comparison UI.
 */
final readonly class PreviewCandidate
{
    /**
     * @param  list<array{signal: string, points: int, label: string, params: array<string, int|string>}>  $parts
     */
    public function __construct(
        public ProductSummary $product,
        public int $score,
        public MatchLevel $level,
        public MatchBucket $bucket,
        public array $parts,
    ) {}
}
