<?php

namespace App\Domain\Reviews\Credibility;

/**
 * The four credibility bands of intel.js `reviewTrust`. The backing value is
 * what `reviews.credibility_level` stores; `label()` is the prototype copy.
 */
enum CredibilityLevel: string
{
    case HighConfidence = 'high_confidence';
    case Normal = 'normal';
    case NeedsReview = 'needs_review';
    case Suspicious = 'suspicious';

    public function label(): string
    {
        return match ($this) {
            self::HighConfidence => 'High confidence',
            self::Normal => 'Normal',
            self::NeedsReview => 'Needs review',
            self::Suspicious => 'Suspicious',
        };
    }
}
