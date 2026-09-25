<?php

namespace App\Domain\Offers;

/**
 * Presence of a merchant listing in its feed (reconciliation, D-25).
 */
enum ListingStatus: string
{
    case Active = 'active';
    /** Absent from at least one published run; deactivated after the threshold. */
    case Missing = 'missing';
    /** Removed by the merchant or by reconciliation; never deleted. */
    case Delisted = 'delisted';
}
