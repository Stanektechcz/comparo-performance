<?php

namespace Database\Factories;

use App\Domain\Orders\OrderSource;
use App\Domain\Orders\OrderStatus;
use App\Models\Country;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Default: a German order created from a decided purchase proof, placed ten
 * days ago, not yet dispatched (29.99 + 4.90 shipping, in EUR minor units).
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_reference' => 'ORD-'.Str::upper(Str::random(12)),
            'user_id' => User::factory(),
            'merchant_id' => Merchant::factory(),
            'country_id' => fn (): int => self::countryId('DE'),
            'currency' => 'EUR',
            'item_total_minor' => 2999,
            'shipping_minor' => 490,
            'total_minor' => 3489,
            'placed_at' => now()->subDays(10),
            'promised_days' => 5,
            'status' => OrderStatus::Placed,
            'source' => OrderSource::PurchaseProof,
            'click_reference' => null,
            'shipped_at' => null,
            'delivered_at' => null,
        ];
    }

    /**
     * Delivered to another market, in that market's currency.
     */
    public function market(string $code): static
    {
        return $this->state(function (array $attributes) use ($code): array {
            $country = Country::query()->where('code', strtoupper($code))->first()
                ?? Country::factory()->code($code)->create();

            return ['country_id' => $country->id, 'currency' => $country->currency->code];
        });
    }

    public function money(int $itemTotalMinor, int $shippingMinor = 0): static
    {
        return $this->state(fn (array $attributes): array => [
            'item_total_minor' => $itemTotalMinor,
            'shipping_minor' => $shippingMinor,
            'total_minor' => $itemTotalMinor + $shippingMinor,
        ]);
    }

    public function fromProof(): static
    {
        return $this->state(fn (array $attributes): array => ['source' => OrderSource::PurchaseProof, 'click_reference' => null]);
    }

    /**
     * Created from an affiliate conversion of a click we logged.
     */
    public function fromConversion(?string $clickReference = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => OrderSource::AffiliateConversion,
            'click_reference' => $clickReference ?? 'clk_'.Str::lower(Str::random(20)),
        ]);
    }

    public function fromClickDeclaration(?string $clickReference = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => OrderSource::ClickDeclaration,
            'click_reference' => $clickReference ?? 'clk_'.Str::lower(Str::random(20)),
        ]);
    }

    public function inTransit(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::InTransit,
            'shipped_at' => now()->subDays(9),
        ]);
    }

    /**
     * Delivered $days after it was placed (projection of the delivery events).
     */
    public function delivered(int $days = 3): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Delivered,
            'placed_at' => now()->subDays(10),
            'shipped_at' => now()->subDays(9),
            'delivered_at' => now()->subDays(10 - $days),
        ]);
    }

    public function returned(): static
    {
        return $this->delivered()->state(fn (array $attributes): array => ['status' => OrderStatus::Returned]);
    }

    public function disputed(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => OrderStatus::Disputed]);
    }

    /**
     * The buyer's account was erased.
     */
    public function withoutUser(): static
    {
        return $this->state(fn (array $attributes): array => ['user_id' => null]);
    }

    private static function countryId(string $code): int
    {
        $id = Country::query()->where('code', $code)->value('id');

        return $id === null ? Country::factory()->code($code)->create()->id : (int) $id;
    }
}
