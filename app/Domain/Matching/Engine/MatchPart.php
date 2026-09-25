<?php

namespace App\Domain\Matching\Engine;

/**
 * One line of a match explanation. Labels are rendered from the signal and its
 * params ({@see MatchPartLabel}); no free text is stored.
 */
final readonly class MatchPart
{
    /**
     * @param  array<string, int|string>  $params  title_similarity: {percent}; pack_differs: {feed_pack, product_pack}
     */
    public function __construct(
        public MatchSignal $signal,
        public int $points,
        public array $params = [],
    ) {}

    /**
     * @return array{signal: string, points: int, params: array<string, int|string>}
     */
    public function toArray(): array
    {
        return ['signal' => $this->signal->value, 'points' => $this->points, 'params' => $this->params];
    }
}
