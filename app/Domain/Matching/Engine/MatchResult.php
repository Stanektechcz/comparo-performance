<?php

namespace App\Domain\Matching\Engine;

/**
 * The best candidate for a feed row, with the evidence that produced its score.
 */
final readonly class MatchResult
{
    /**
     * @param  ?int  $bestProductId  null only when there were no candidates
     * @param  int  $score  clamped to 0–100
     * @param  int  $rawPoints  sum of all parts before clamping
     * @param  list<MatchPart>  $parts  in evaluation order, zero-point parts omitted
     */
    public function __construct(
        public ?int $bestProductId,
        public int $score,
        public int $rawPoints,
        public MatchLevel $level,
        public MatchBucket $bucket,
        public array $parts,
        public string $policyVersion,
        public string $algorithm,
    ) {}

    /**
     * @return list<string>
     */
    public function englishLabels(): array
    {
        return array_map(MatchPartLabel::english(...), $this->parts);
    }
}
