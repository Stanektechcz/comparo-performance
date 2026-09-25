<?php

namespace App\Domain\Pricing\LandedPrice;

use App\Domain\Pricing\CouponState;
use App\Domain\Pricing\CouponType;
use DateTimeImmutable;

/**
 * The terms of one merchant coupon, as needed to decide applicability.
 */
final readonly class CouponTerms
{
    /**
     * @param  int|null  $percentOffBasisPoints  1000 = 10 %
     * @param  list<string>  $marketCodes  ISO-2 markets the coupon is valid in
     */
    public function __construct(
        public int $id,
        public string $code,
        public ?string $title,
        public CouponType $type,
        public ?int $percentOffBasisPoints,
        public ?int $amountOffMinor,
        public int $minOrderMinor,
        public string $currency,
        public array $marketCodes,
        public ?DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public CouponState $state,
        public bool $exclusive = false,
    ) {}

    /**
     * The prototype checks market, end date, verification state and minimum
     * order against the single-unit price. It never checked the start date;
     * a coupon that has not started yet is not applicable here (no seed
     * coupon starts in the future, so parity is unaffected).
     */
    public function isApplicable(string $marketCode, int $priceMinor, DateTimeImmutable $now): bool
    {
        return in_array($marketCode, $this->marketCodes, true)
            && ($this->startsAt === null || $this->startsAt <= $now)
            && $this->endsAt > $now
            && $this->state->isUsable()
            && $priceMinor >= $this->minOrderMinor;
    }
}
