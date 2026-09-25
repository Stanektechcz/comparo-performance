<?php

namespace App\Domain\Feeds\Exceptions;

use DomainException;

/**
 * A source already has a non-terminal run (partial unique index
 * `feed_runs_single_active`): at most one run per source at a time.
 */
final class FeedRunAlreadyActive extends DomainException
{
    public function __construct(public readonly int $activeRunId)
    {
        parent::__construct("Feed run {$activeRunId} of this source is still in progress.");
    }
}
