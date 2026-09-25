<?php

namespace App\Models\Concerns;

use App\Domain\Platform\Exceptions\AppendOnlyViolation;

/**
 * Application-level guard for append-only histories. The database enforces
 * the same rule with triggers, so raw queries cannot bypass it either.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(static fn () => throw AppendOnlyViolation::for(static::class, 'update'));
        static::deleting(static fn () => throw AppendOnlyViolation::for(static::class, 'delete'));
    }
}
