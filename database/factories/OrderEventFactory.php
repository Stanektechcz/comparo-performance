<?php

namespace Database\Factories;

use App\Domain\Orders\EventSource;
use App\Domain\Orders\OrderEventType;
use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: the system records that the order was placed. Rows are append-only.
 *
 * @extends Factory<OrderEvent>
 */
class OrderEventFactory extends Factory
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
            'type' => OrderEventType::Placed,
            'source' => EventSource::System,
            'details' => null,
            'occurred_at' => now()->subDays(10),
            'provisional_until' => null,
            'supersedes_id' => null,
        ];
    }

    public function type(OrderEventType $type): static
    {
        return $this->state(fn (array $attributes): array => ['type' => $type, 'occurred_at' => now()]);
    }

    /**
     * Reported by the shopper: provisional for 48 h.
     */
    public function fromShopper(): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => EventSource::Shopper,
            'provisional_until' => now()->addHours(48),
        ]);
    }

    /**
     * A correction superseding an earlier event of the same order.
     */
    public function corrects(OrderEvent $event): static
    {
        return $this->state(fn (array $attributes): array => [
            'order_id' => $event->order_id,
            'type' => OrderEventType::Corrected,
            'source' => EventSource::Staff,
            'details' => ['corrected_type' => $event->type->value],
            'occurred_at' => now(),
            'supersedes_id' => $event->id,
        ]);
    }
}
