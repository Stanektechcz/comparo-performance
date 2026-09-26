<?php

namespace App\Domain\Reviews\Credibility;

/**
 * The penalties of intel.js `reviewTrust`, in the order the prototype
 * evaluates (and lists) them. Points live in {@see CredibilityPolicy}.
 */
enum CredibilityPenalty: string
{
    case DuplicateText = 'duplicate_text';
    case Burst = 'burst';
    case YoungAccount = 'young_account';
    case Unverified = 'unverified';
    case SharedDevice = 'shared_device';
    case RepeatedTarget = 'repeated_target';
    case ShortBody = 'short_body';

    public function label(): string
    {
        return match ($this) {
            self::DuplicateText => 'Duplicate text',
            self::Burst => 'Arrived inside a review burst',
            self::YoungAccount => 'Very young account',
            self::Unverified => 'No verified purchase',
            self::SharedDevice => 'Shared device fingerprint',
            self::RepeatedTarget => 'Repeated target',
            self::ShortBody => 'Very short body',
        };
    }
}
