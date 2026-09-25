<?php

namespace App\Domain\Feeds\Exceptions;

use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\FeedSourceStatus;
use App\Domain\Feeds\Lifecycle\FeedActorKind;
use DomainException;

/**
 * A feed source or feed run transition the state machine does not allow
 * (docs/architecture/phase-2-feeds-matching.md §4).
 */
final class InvalidFeedTransition extends DomainException
{
    public static function forSource(FeedSourceStatus $from, FeedSourceStatus $to, FeedActorKind $actor): self
    {
        return new self("A feed source cannot move from [{$from->value}] to [{$to->value}] by [{$actor->value}].");
    }

    public static function forRun(FeedRunStatus $from, FeedRunStatus $to): self
    {
        return new self("A feed run cannot move from [{$from->value}] to [{$to->value}].");
    }
}
