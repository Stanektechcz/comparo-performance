<?php

namespace App\Domain\Merchants\Risk;

/**
 * Internal merchant integrity level. Never exposed to merchants or the public.
 */
enum RiskLevel: string
{
    case Low = 'LOW';
    case Medium = 'MEDIUM';
    case High = 'HIGH';
    case Critical = 'CRITICAL';

    public static function forScore(int $score): self
    {
        return match (true) {
            $score >= 62 => self::Critical,
            $score >= 42 => self::High,
            $score >= 22 => self::Medium,
            default => self::Low,
        };
    }

    /**
     * Points a single risk event of this severity contributes (before the 0.55 factor).
     */
    public function eventWeight(): int
    {
        return match ($this) {
            self::Low => 4,
            self::Medium => 10,
            self::High => 18,
            self::Critical => 30,
        };
    }
}
