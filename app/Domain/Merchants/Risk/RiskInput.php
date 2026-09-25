<?php

namespace App\Domain\Merchants\Risk;

/**
 * Measured integrity inputs for one merchant. Null means "not measured";
 * a signal without a measurement contributes nothing (as in the prototype,
 * where a missing value produced NaN and was skipped).
 */
final readonly class RiskInput
{
    /**
     * @param  list<array{kind: string, severity: RiskLevel, description: string}>  $events  unresolved events
     */
    public function __construct(
        public bool $businessVerified,
        public ?float $complaintRate,
        public ?float $feedUptime,
        public ?float $brokenLinkRate,
        public ?float $priceAccuracy,
        public ?int $communityReports,
        public ?float $responseRate,
        public array $events = [],
    ) {}
}
