<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: one unit of a product at 29.99 in the order's currency.
 *
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'offer_id' => null,
            'quantity' => 1,
            'unit_price_minor' => 2999,
            'line_total_minor' => 2999,
            'currency' => fn (array $attributes): string => (string) (Order::query()->whereKey($attributes['order_id'])->value('currency') ?? 'EUR'),
        ];
    }

    public function quantity(int $quantity, int $unitPriceMinor = 2999): static
    {
        return $this->state(fn (array $attributes): array => [
            'quantity' => $quantity,
            'unit_price_minor' => $unitPriceMinor,
            'line_total_minor' => $quantity * $unitPriceMinor,
        ]);
    }
}
