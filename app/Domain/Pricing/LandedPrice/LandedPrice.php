<?php

namespace App\Domain\Pricing\LandedPrice;

use App\Domain\Shared\Money;

/**
 * The server-evaluated landed total of one offer in one market:
 * effective price (after the best applicable coupon) + shipping to the market.
 */
final readonly class LandedPrice
{
    public function __construct(
        public bool $ships,
        public Money $basePrice,
        public ?AppliedCoupon $coupon,
        public Money $discount,
        public Money $effectivePrice,
        public Money $shipping,
        public ShippingBasis $shippingBasis,
        public Money $total,
        public ?Money $freeShippingThreshold,
        public ?int $deliveryMinDays,
        public ?int $deliveryMaxDays,
        public ?string $carrier,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ships' => $this->ships,
            'currency' => $this->total->currency,
            'base_price' => $this->basePrice->minor,
            'discount' => $this->discount->minor,
            'effective_price' => $this->effectivePrice->minor,
            'shipping' => $this->shipping->minor,
            'shipping_basis' => $this->shippingBasis->value,
            'total' => $this->total->minor,
            'free_shipping_threshold' => $this->freeShippingThreshold?->minor,
            'coupon' => $this->coupon === null ? null : [
                'code' => $this->coupon->code,
                'title' => $this->coupon->title,
                'type' => $this->coupon->type->value,
                'state' => $this->coupon->state->value,
                'state_label' => $this->coupon->state->label(),
                'exclusive' => $this->coupon->exclusive,
                'saving' => $this->coupon->savingMinor,
            ],
            'delivery_days' => $this->deliveryMaxDays === null ? null : [$this->deliveryMinDays, $this->deliveryMaxDays],
            'carrier' => $this->carrier,
        ];
    }
}
