<?php

namespace App\Domain\Pricing;

/**
 * Coupon verification state (intel.js couponStateMeta).
 */
enum CouponState: string
{
    case Verified = 'verified';
    case Merchant = 'merchant';
    case Community = 'community';
    case Unverified = 'unverified';
    case Expired = 'expired';
    case Invalid = 'invalid';

    public function isUsable(): bool
    {
        return $this !== self::Expired && $this !== self::Invalid;
    }

    public function label(): string
    {
        return match ($this) {
            self::Verified => 'Verified',
            self::Merchant => 'Merchant verified',
            self::Community => 'Community verified',
            self::Unverified => 'Unverified',
            self::Expired => 'Expired',
            self::Invalid => 'Invalid',
        };
    }

    public function explanation(): string
    {
        return match ($this) {
            self::Verified => 'Checked by the Comparo data team',
            self::Merchant => 'Confirmed by the merchant',
            self::Community => 'Confirmed by user reports',
            self::Unverified => 'Not enough reports yet',
            self::Expired => 'End date has passed',
            self::Invalid => 'Reported as not working',
        };
    }
}
