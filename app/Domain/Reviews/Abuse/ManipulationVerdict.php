<?php

namespace App\Domain\Reviews\Abuse;

/**
 * The verdict of intel.js `manipulation`: decided by the most recent spike.
 */
enum ManipulationVerdict: string
{
    case None = 'none';
    case Positive = 'positive';
    case Negative = 'negative';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No manipulation pattern detected',
            self::Positive => 'Positive manipulation pattern',
            self::Negative => 'Negative campaign pattern',
        };
    }
}
