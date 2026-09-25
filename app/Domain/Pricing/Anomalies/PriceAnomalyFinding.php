<?php

namespace App\Domain\Pricing\Anomalies;

use App\Domain\Pricing\PriceAnomaly;

/**
 * A flagged price and the market median it was judged against (minor units,
 * stored as offers.anomaly_reference_minor).
 */
final readonly class PriceAnomalyFinding
{
    public function __construct(
        public PriceAnomaly $anomaly,
        public int $medianMinor,
    ) {}
}
