<?php

namespace App\Domain\Offers;

/**
 * Why an offer was deactivated (offers.deactivation_reason).
 */
enum OfferDeactivationReason: string
{
    case MissingFromFeed = 'missing_from_feed';
    case NotSeen = 'not_seen';
    case ComplianceHold = 'compliance_hold';
    case SourcePaused = 'source_paused';
    case Manual = 'manual';
    case Delisted = 'delisted';
}
