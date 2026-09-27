<?php

namespace Database\Factories;

use App\Domain\Orders\ReturnStatus;
use App\Models\Order;
use App\Models\OrderReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: a return just requested for a delivered order.
 *
 * @extends Factory<OrderReturn>
 */
class OrderReturnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->delivered(),
            'status' => ReturnStatus::Requested,
            'reason_code' => 'changed_mind',
            'note' => null,
            'refund_minor' => null,
            'currency' => null,
            'requested_at' => now(),
            'sent_back_at' => null,
            'resolved_at' => null,
        ];
    }

    public function sentBack(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => ReturnStatus::SentBack, 'sent_back_at' => now()]);
    }

    public function refunded(int $refundMinor = 2999, string $currency = 'EUR'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReturnStatus::Refunded,
            'sent_back_at' => now()->subDays(3),
            'resolved_at' => now(),
            'refund_minor' => $refundMinor,
            'currency' => $currency,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => ReturnStatus::Rejected, 'resolved_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => ReturnStatus::Cancelled, 'resolved_at' => now()]);
    }
}
