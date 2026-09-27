<?php

namespace Database\Factories;

use App\Domain\Orders\DeliveryEventType;
use App\Domain\Orders\EventSource;
use App\Models\DeliveryEvent;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: the merchant reports the parcel dispatched with DHL. Rows are append-only.
 *
 * @extends Factory<DeliveryEvent>
 */
class DeliveryEventFactory extends Factory
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
            'type' => DeliveryEventType::Dispatched,
            'source' => EventSource::Merchant,
            'carrier' => 'DHL',
            'details' => null,
            'occurred_at' => now()->subDays(9),
            'provisional_until' => null,
            'supersedes_id' => null,
        ];
    }

    public function inTransit(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => DeliveryEventType::InTransit, 'source' => EventSource::Carrier, 'occurred_at' => now()->subDays(8)]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => DeliveryEventType::Delivered, 'occurred_at' => now()->subDays(7)]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => DeliveryEventType::DeliveryFailed, 'source' => EventSource::Carrier, 'occurred_at' => now()->subDays(7)]);
    }

    /**
     * Delivery reported by the shopper: provisional for 48 h.
     */
    public function reportedByShopper(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => DeliveryEventType::Delivered,
            'source' => EventSource::Shopper,
            'carrier' => null,
            'occurred_at' => now(),
            'provisional_until' => now()->addHours(48),
        ]);
    }

    /**
     * A correction superseding an earlier event of the same order.
     */
    public function corrects(DeliveryEvent $event): static
    {
        return $this->state(fn (array $attributes): array => [
            'order_id' => $event->order_id,
            'type' => $event->type,
            'source' => EventSource::Staff,
            'details' => ['reason' => 'wrong_date'],
            'occurred_at' => $event->occurred_at->copy()->addDay(),
            'supersedes_id' => $event->id,
        ]);
    }
}
