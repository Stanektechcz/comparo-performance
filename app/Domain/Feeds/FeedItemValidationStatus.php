<?php

namespace App\Domain\Feeds;

/**
 * Validation verdict of one staged feed row.
 */
enum FeedItemValidationStatus: string
{
    case Valid = 'valid';
    /** Published, but carries at least one row warning (e.g. INVALID_GTIN). */
    case Warning = 'warning';
    /** Rejected; never published. */
    case Invalid = 'invalid';

    public function isPublishable(): bool
    {
        return $this !== self::Invalid;
    }
}
