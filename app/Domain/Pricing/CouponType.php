<?php

namespace App\Domain\Pricing;

enum CouponType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case FreeShipping = 'free_shipping';
}
