<?php

namespace App\Domain\Matching\Engine;

/**
 * Confidence level of a match; cut-offs come from {@see MatchingPolicy::levelFor()}.
 */
enum MatchLevel: string
{
    case Exact = 'exact';
    case VeryHigh = 'very_high';
    case High = 'high';
    case Possible = 'possible';
    case ManualReview = 'manual_review';

    /**
     * The prototype's English label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Exact => 'Exact',
            self::VeryHigh => 'Very high',
            self::High => 'High',
            self::Possible => 'Possible',
            self::ManualReview => 'Manual review',
        };
    }
}
