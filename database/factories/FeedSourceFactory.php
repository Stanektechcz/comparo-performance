<?php

namespace Database\Factories;

use App\Domain\Feeds\FeedFormat;
use App\Domain\Feeds\FeedSourceStatus;
use App\Domain\Feeds\FeedTransport;
use App\Models\FeedSource;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Default: a draft CSV feed fetched from a URL every six hours, no credentials.
 *
 * @extends Factory<FeedSource>
 */
class FeedSourceFactory extends Factory
{
    public const int DEFAULT_INTERVAL_MINUTES = 360;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'name' => 'Feed '.Str::upper(Str::random(8)),
            'format' => FeedFormat::Csv,
            'transport' => FeedTransport::Url,
            'status' => FeedSourceStatus::Draft,
            'status_reason' => null,
            'url' => 'https://feeds.example.com/'.Str::lower(Str::random(10)).'.csv',
            'country_id' => null,
            'currency' => 'EUR',
            'encoding' => 'UTF-8',
            'delimiter' => ',',
            'record_element' => null,
            'availability_map' => null,
            'interval_minutes' => self::DEFAULT_INTERVAL_MINUTES,
            'last_run_at' => null,
            'last_success_at' => null,
            'next_run_at' => null,
            'last_checksum' => null,
            'consecutive_failures' => 0,
        ];
    }

    public function csv(): static
    {
        return $this->state(fn (array $attributes): array => [
            'format' => FeedFormat::Csv,
            'delimiter' => ',',
            'record_element' => null,
        ]);
    }

    public function xml(): static
    {
        return $this->state(fn (array $attributes): array => [
            'format' => FeedFormat::Xml,
            'delimiter' => null,
            'record_element' => 'item',
        ]);
    }

    public function json(): static
    {
        return $this->state(fn (array $attributes): array => [
            'format' => FeedFormat::Json,
            'delimiter' => null,
            'record_element' => 'products',
        ]);
    }

    public function url(?string $url = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'transport' => FeedTransport::Url,
            'url' => $url ?? 'https://feeds.example.com/'.Str::lower(Str::random(10)).'.csv',
        ]);
    }

    /**
     * Merchant-uploaded files: no URL, not scheduled.
     */
    public function upload(): static
    {
        return $this->state(fn (array $attributes): array => [
            'transport' => FeedTransport::Upload,
            'url' => null,
            'interval_minutes' => null,
            'next_run_at' => null,
        ]);
    }

    /**
     * Has completed at least one successful run.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FeedSourceStatus::Active,
            'status_reason' => null,
            'last_run_at' => now()->subHour(),
            'last_success_at' => now()->subHour(),
            'next_run_at' => now()->addMinutes(self::DEFAULT_INTERVAL_MINUTES - 60),
            'last_checksum' => hash('sha256', Str::random(32)),
            'consecutive_failures' => 0,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FeedSourceStatus::Paused,
            'next_run_at' => null,
        ]);
    }

    /**
     * Moved to error after consecutive failed runs.
     */
    public function erroring(int $consecutiveFailures = 3, string $reason = 'FETCH_TIMEOUT'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FeedSourceStatus::Error,
            'status_reason' => $reason,
            'consecutive_failures' => $consecutiveFailures,
            'last_run_at' => now()->subHour(),
            'next_run_at' => null,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FeedSourceStatus::Disabled,
            'status_reason' => 'staff',
            'next_run_at' => null,
        ]);
    }

    /**
     * Active and due for its scheduled run.
     */
    public function due(): static
    {
        return $this->active()->state(fn (array $attributes): array => [
            'next_run_at' => now()->subMinute(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function withCredentials(array $credentials = ['username' => 'feed-user', 'password' => 'feed-secret']): static
    {
        return $this->state(fn (array $attributes): array => ['credentials' => $credentials]);
    }
}
