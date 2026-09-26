<?php

namespace Database\Factories;

use App\Domain\Orders\DisputeStatus;
use App\Models\Order;
use App\Models\OrderDispute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: an open dispute about a parcel marked delivered that never arrived.
 *
 * @extends Factory<OrderDispute>
 */
class OrderDisputeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->disputed(),
            'status' => DisputeStatus::Open,
            'reason_code' => 'not_received',
            'note' => null,
            'opened_at' => now(),
            'expires_at' => now()->addDays(30),
            'resolved_at' => null,
            'resolution_code' => null,
        ];
    }

    public function resolved(string $resolutionCode = 'refunded'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DisputeStatus::Resolved,
            'resolved_at' => now(),
            'resolution_code' => $resolutionCode,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DisputeStatus::Expired,
            'opened_at' => now()->subDays(31),
            'expires_at' => now()->subDay(),
        ]);
    }
}
