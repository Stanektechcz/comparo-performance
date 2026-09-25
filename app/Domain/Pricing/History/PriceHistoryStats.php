<?php

namespace App\Domain\Pricing\History;

/**
 * Statistics over a daily lowest-price series (minor units).
 */
final readonly class PriceHistoryStats
{
    public function __construct(
        public int $average7,
        public int $average30,
        public int $average90,
        public int $average365,
        public int $low,
        public int $high,
        public int $median,
        public int $low30,
        public int $low90,
        public float $volatilityPercent,
        public int $current,
        public int $days,
    ) {}

    /**
     * @return array<string, int|float>
     */
    public function toArray(): array
    {
        return [
            'average_7' => $this->average7,
            'average_30' => $this->average30,
            'average_90' => $this->average90,
            'average_365' => $this->average365,
            'low' => $this->low,
            'high' => $this->high,
            'median' => $this->median,
            'low_30' => $this->low30,
            'low_90' => $this->low90,
            'volatility_percent' => $this->volatilityPercent,
            'current' => $this->current,
            'days' => $this->days,
        ];
    }
}
