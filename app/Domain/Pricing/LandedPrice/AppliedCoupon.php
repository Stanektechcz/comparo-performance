<?php

namespace App\Domain\Pricing\LandedPrice;

use App\Domain\Pricing\CouponState;
use App\Domain\Pricing\CouponType;

final readonly class AppliedCoupon
{
    public function __construct(
        public int $couponId,
        public string $code,
        public ?string $title,
        public CouponType $type,
        public CouponState $state,
        public bool $exclusive,
        public int $savingMinor,
    ) {}
}
