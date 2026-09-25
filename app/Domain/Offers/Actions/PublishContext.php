<?php

namespace App\Domain\Offers\Actions;

use App\Domain\Pricing\History\SnapshotSource;
use DateTimeImmutable;

/**
 * Where and when an offer observation came from.
 */
final readonly class PublishContext
{
    public function __construct(
        public SnapshotSource $source,
        public ?int $feedRunId,
        public DateTimeImmutable $observedAt,
    ) {}
}
