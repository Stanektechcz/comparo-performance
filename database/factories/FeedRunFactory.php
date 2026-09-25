<?php

namespace Database\Factories;

use App\Domain\Feeds\FeedRunOutcome;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\FeedRunTrigger;
use App\Models\FeedRun;
use App\Models\FeedSource;
use App\Models\MatchingPolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Default: a queued manual run of a new source under the active matching
 * policy. The run's merchant is always its source's merchant. Only one
 * non-terminal run may exist per source — use completed()/failed()/cancelled()
 * for history rows.
 *
 * @extends Factory<FeedRun>
 */
class FeedRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feed_source_id' => FeedSource::factory(),
            'merchant_id' => fn (array $attributes): int => (int) FeedSource::query()
                ->whereKey($attributes['feed_source_id'])
                ->value('merchant_id'),
            'feed_mapping_id' => null,
            'matching_policy_id' => fn (): ?int => MatchingPolicy::query()->where('is_active', true)->value('id'),
            'trigger' => FeedRunTrigger::Manual,
            'triggered_by_user_id' => null,
            'status' => FeedRunStatus::Queued,
            'outcome' => null,
            'idempotency_key' => fn (): string => 'manual:'.Str::uuid(),
            'checksum' => null,
            'correlation_id' => fn (): string => (string) Str::uuid(),
            'payload_path' => null,
            'payload_bytes' => null,
        ];
    }

    public function forSource(FeedSource $source): static
    {
        return $this->state(fn (array $attributes): array => [
            'feed_source_id' => $source->id,
            'merchant_id' => $source->merchant_id,
        ]);
    }

    public function queued(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FeedRunStatus::Queued,
            'outcome' => null,
            'started_at' => null,
        ]);
    }

    /**
     * In one of the non-terminal pipeline stages.
     */
    public function running(FeedRunStatus $status = FeedRunStatus::Fetching): static
    {
        if ($status->isTerminal()) {
            throw new InvalidArgumentException("[{$status->value}] is a terminal feed run status.");
        }

        return $this->state(fn (array $attributes): array => [
            'status' => $status,
            'outcome' => null,
            'started_at' => now()->subMinutes(2),
        ]);
    }

    /**
     * @param  array<string, int>  $metrics  counter columns (FeedRun::METRICS) to override
     */
    public function completed(FeedRunOutcome $outcome = FeedRunOutcome::Published, array $metrics = []): static
    {
        $unknown = array_diff(array_keys($metrics), FeedRun::METRICS);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown feed run metrics: '.implode(', ', $unknown));
        }

        $published = $outcome !== FeedRunOutcome::Unchanged;

        return $this->state(fn (array $attributes): array => [
            'status' => FeedRunStatus::Completed,
            'outcome' => $outcome,
            'checksum' => hash('sha256', Str::random(32)),
            'payload_path' => 'feeds/'.Str::uuid().'.csv',
            'payload_bytes' => 20480,
            'started_at' => now()->subMinutes(5),
            'fetched_at' => now()->subMinutes(5),
            'parsed_at' => $published ? now()->subMinutes(4) : null,
            'normalized_at' => $published ? now()->subMinutes(4) : null,
            'matched_at' => $published ? now()->subMinutes(3) : null,
            'published_at' => $published ? now()->subMinutes(2) : null,
            'finished_at' => now()->subMinutes(2),
            'duration_ms' => 180000,
            ...($published ? ['rows_read' => 10, 'rows_valid' => 10, 'rows_matched' => 10, 'offers_updated' => 10] : []),
            ...$metrics,
        ]);
    }

    public function failed(string $code = 'FETCH_TIMEOUT'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FeedRunStatus::Failed,
            'outcome' => null,
            'failure_code' => $code,
            'failure_reason' => null,
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(2),
            'errors' => 1,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FeedRunStatus::Cancelled,
            'outcome' => null,
            'finished_at' => now()->subMinute(),
        ]);
    }

    public function manual(?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'trigger' => FeedRunTrigger::Manual,
            'triggered_by_user_id' => $user?->id,
            'idempotency_key' => 'manual:'.Str::uuid(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'trigger' => FeedRunTrigger::Schedule,
            'triggered_by_user_id' => null,
            'idempotency_key' => fn (array $resolved): string => 'schedule:'.$resolved['feed_source_id'].':'.Str::uuid(),
        ]);
    }

    public function api(): static
    {
        return $this->state(fn (array $attributes): array => [
            'trigger' => FeedRunTrigger::Api,
            'triggered_by_user_id' => null,
            'idempotency_key' => 'api:'.Str::uuid(),
        ]);
    }
}
