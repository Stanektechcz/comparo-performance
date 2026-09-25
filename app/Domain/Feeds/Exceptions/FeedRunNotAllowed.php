<?php

namespace App\Domain\Feeds\Exceptions;

use App\Domain\Feeds\FeedRunTrigger;
use App\Domain\Feeds\FeedSourceStatus;
use DomainException;

/**
 * A run may not start now: the source's status does not allow this trigger,
 * or a manual run was started too recently (cooldown).
 */
final class FeedRunNotAllowed extends DomainException
{
    private function __construct(string $message, public readonly ?int $retryAfterSeconds = null)
    {
        parent::__construct($message);
    }

    public static function forStatus(FeedSourceStatus $status, FeedRunTrigger $trigger): self
    {
        return new self("A [{$trigger->value}] run cannot start while the feed source is [{$status->value}].");
    }

    public static function coolingDown(int $retryAfterSeconds): self
    {
        return new self("A manual run was started recently; try again in {$retryAfterSeconds} seconds.", $retryAfterSeconds);
    }

    public static function missingPayload(): self
    {
        return new self('An uploaded feed needs its stored payload to start a run.');
    }
}
