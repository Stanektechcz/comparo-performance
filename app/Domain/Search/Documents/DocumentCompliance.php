<?php

namespace App\Domain\Search\Documents;

use App\Domain\Compliance\ComplianceStatus;

/**
 * The public compliance vocabulary of search documents
 * (docs/architecture/phase-3-search.md §2): prescription-only and
 * not-allowed are both `blocked`; the reason stays internal.
 */
enum DocumentCompliance: string
{
    case Allowed = 'allowed';
    case Restricted = 'restricted';
    case Unknown = 'unknown';
    case Blocked = 'blocked';

    public static function fromStatus(ComplianceStatus $status): self
    {
        return match ($status) {
            ComplianceStatus::Allowed => self::Allowed,
            ComplianceStatus::Restricted => self::Restricted,
            ComplianceStatus::Unknown => self::Unknown,
            ComplianceStatus::PrescriptionOnly, ComplianceStatus::NotAllowed => self::Blocked,
        };
    }

    /**
     * A status with the same serialization policy (blocked maps to
     * not-allowed: offers invisible, not purchasable).
     */
    public function status(): ComplianceStatus
    {
        return match ($this) {
            self::Allowed => ComplianceStatus::Allowed,
            self::Restricted => ComplianceStatus::Restricted,
            self::Unknown => ComplianceStatus::Unknown,
            self::Blocked => ComplianceStatus::NotAllowed,
        };
    }

    public function isBlocked(): bool
    {
        return $this === self::Blocked;
    }
}
