<?php

namespace App\Domain\Offers\Ranking;

enum RankBand: string
{
    case Exceptional = 'exceptional';
    case Excellent = 'excellent';
    case Good = 'good';
    case Fair = 'fair';
    case LowConfidence = 'low';

    public static function forScore(int $score): self
    {
        return match (true) {
            $score >= 90 => self::Exceptional,
            $score >= 80 => self::Excellent,
            $score >= 70 => self::Good,
            $score >= 60 => self::Fair,
            default => self::LowConfidence,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Exceptional => 'Exceptional',
            self::Excellent => 'Excellent',
            self::Good => 'Good',
            self::Fair => 'Fair',
            self::LowConfidence => 'Low confidence',
        };
    }
}
