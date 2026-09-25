<?php

namespace Database\Factories;

use App\Domain\Pricing\CouponState;
use App\Domain\Pricing\CouponType;
use App\Models\Coupon;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Default: a verified 10 % coupon, running now, no minimum order.
 *
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'code' => 'SAVE'.Str::upper(Str::random(6)),
            'title' => '10 % off your order',
            'type' => CouponType::Percent,
            'percent_off' => 10,
            'amount_off_minor' => null,
            'currency' => 'EUR',
            'min_order_minor' => 0,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
            'is_exclusive' => false,
            'verification_state' => CouponState::Verified,
            'last_verified_at' => now()->subDay(),
            'verified_by' => 'Comparo data team',
            'reports_worked' => 0,
            'reports_failed' => 0,
        ];
    }

    public function percent(int $percent): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CouponType::Percent,
            'percent_off' => $percent,
            'amount_off_minor' => null,
            'title' => "{$percent} % off your order",
        ]);
    }

    /**
     * @param  int  $amountMinor  discount in minor units of the coupon currency
     */
    public function fixed(int $amountMinor): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CouponType::Fixed,
            'percent_off' => null,
            'amount_off_minor' => $amountMinor,
            'title' => 'Fixed amount off your order',
        ]);
    }

    public function freeShipping(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CouponType::FreeShipping,
            'percent_off' => null,
            'amount_off_minor' => null,
            'title' => 'Free shipping',
        ]);
    }

    public function minOrder(int $minorUnits): static
    {
        return $this->state(fn (array $attributes): array => ['min_order_minor' => $minorUnits]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->subDay(),
            'verification_state' => CouponState::Expired,
        ]);
    }

    public function invalid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'verification_state' => CouponState::Invalid,
            'reports_failed' => 25,
        ]);
    }

    public function notStarted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(30),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'verification_state' => CouponState::Unverified,
            'last_verified_at' => null,
            'verified_by' => null,
        ]);
    }
}
