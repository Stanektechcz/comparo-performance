<?php

namespace App\Domain\Compliance;

/**
 * Per-market product compliance status and the serialization policy each
 * status implies. The policy is applied server-side before any offer is
 * serialized, cached, indexed, recommended or notified
 * (docs/adr/0007-compliance-before-serialization.md).
 */
enum ComplianceStatus: string
{
    case Allowed = 'allowed';
    case Restricted = 'restricted';
    case PrescriptionOnly = 'prescription_only';
    case NotAllowed = 'not_allowed';
    case Unknown = 'unknown';

    /**
     * Whether offer prices may be listed at all.
     */
    public function offersVisible(): bool
    {
        return match ($this) {
            self::Allowed, self::Restricted, self::Unknown => true,
            self::PrescriptionOnly, self::NotAllowed => false,
        };
    }

    /**
     * Whether a purchase CTA / outbound affiliate link may be offered.
     * Unknown is the safe default: prices are informational only.
     */
    public function isPurchasable(): bool
    {
        return $this === self::Allowed || $this === self::Restricted;
    }

    /**
     * Whether the product may be recommended automatically (home, "for you",
     * e-mail, alerts, sponsored placements).
     */
    public function isRecommendable(): bool
    {
        return $this === self::Allowed;
    }

    public function requiresReview(): bool
    {
        return $this === self::Unknown;
    }

    /**
     * Blocked for purchase in the market (ComparoRank `complianceBlocked`).
     */
    public function isBlocked(): bool
    {
        return $this === self::PrescriptionOnly || $this === self::NotAllowed;
    }

    public function label(): string
    {
        return match ($this) {
            self::Allowed => 'Allowed',
            self::Restricted => 'Restricted',
            self::PrescriptionOnly => 'Prescription only',
            self::NotAllowed => 'Not allowed',
            self::Unknown => 'Not yet reviewed',
        };
    }
}
