<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Contracts\ComplianceHoldCheck;
use DateTimeImmutable;

/**
 * How one listing is matched: the feed run it belongs to (stored on the
 * decision), the market's compliance check, the decision time (the action
 * never reads the clock) and whether reusable decisions are ignored.
 */
final readonly class MatchContext
{
    public function __construct(
        public ?int $feedRunId,
        public ?ComplianceHoldCheck $complianceHold,
        public DateTimeImmutable $decidedAt,
        public bool $forceRematch = false,
    ) {}
}
