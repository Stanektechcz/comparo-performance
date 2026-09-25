<?php

namespace App\Domain\Pricing\LandedPrice;

/**
 * Why the shipping amount is what it is — part of the price explanation.
 */
enum ShippingBasis: string
{
    case NotShippingToMarket = 'not_shipping_to_market';
    case ZoneRate = 'zone_rate';
    case FreeOverThreshold = 'free_over_threshold';
    case FreeShippingCoupon = 'free_shipping_coupon';
}
