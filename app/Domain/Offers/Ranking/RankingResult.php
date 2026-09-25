<?php

namespace App\Domain\Offers\Ranking;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * A fully explainable ComparoRank: final score, the points behind it, the
 * visible deductions, the weights version used and when it was evaluated.
 */
final readonly class RankingResult
{
    /**
     * @param  list<RankingPart>  $parts  non-zero parts, highest points first
     * @param  list<RankingPenalty>  $penalties  visible penalties only
     */
    public function __construct(
        public int $score,
        public RankBand $band,
        public array $parts,
        public array $penalties,
        public int $hiddenPenaltyCount,
        public bool $eligibleBestBuy,
        public RankingWeights $weights,
        public DateTimeImmutable $evaluatedAt,
    ) {}

    /**
     * Public explanation payload ("Why this rank?"). Hidden penalty labels are
     * never serialised — only how many integrity checks were withheld.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'band' => $this->band->value,
            'label' => $this->band->label(),
            'parts' => array_map(static fn (RankingPart $part): array => $part->toArray(), $this->parts),
            'penalties' => array_map(static fn (RankingPenalty $penalty): array => $penalty->toArray(), $this->penalties),
            'withheld_checks' => $this->hiddenPenaltyCount,
            'eligible_best_buy' => $this->eligibleBestBuy,
            'version' => $this->weights->version,
            'weights' => $this->weights->toArray(),
            'evaluated_at' => $this->evaluatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
