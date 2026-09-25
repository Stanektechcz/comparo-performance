<?php

namespace App\Domain\Pricing;

/**
 * A price flagged by anomaly detection. A flagged offer gets no coupon,
 * scores 0 on price, is never best-buy eligible and is not published.
 */
enum PriceAnomaly: string
{
    case TooLow = 'too_low';
    case TooHigh = 'too_high';
}
