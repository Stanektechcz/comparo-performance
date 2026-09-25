<?php

namespace App\Domain\Search\Analytics;

use App\Domain\Search\Events\SearchPerformed;
use App\Domain\Search\Events\ZeroResultSearchRecorded;
use App\Models\SearchQuery as SearchQueryRecord;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Writes one prepared search_queries row (queue `analytics`, dispatched by
 * RecordSearch). Idempotent: a retried job whose row already exists does
 * nothing. Carries only redacted, pseudonymous values — never an IP
 * address, a user id, a raw query or a raw session id.
 */
final class StoreSearchQuery implements ShouldQueue
{
    use Queueable;

    public const string DATE_FORMAT = 'Y-m-d H:i:s.u';

    public int $tries = 3;

    /**
     * @param  ?array<string, mixed>  $filters
     * @param  list<array{type: string, id: int}>  $resultRefs
     */
    public function __construct(
        public readonly string $searchId,
        public readonly string $occurredAt,
        public readonly string $market,
        public readonly string $locale,
        public readonly string $source,
        public readonly string $queryNormalized,
        public readonly string $queryHash,
        public readonly ?array $filters,
        public readonly int $resultCount,
        public readonly array $resultRefs,
        public readonly ?string $sessionHash,
        public readonly bool $isBot,
    ) {}

    public function handle(): void
    {
        if (SearchQueryRecord::query()->whereKey($this->searchId)->exists()) {
            return;
        }

        $record = new SearchQueryRecord;
        $record->forceFill([
            'search_id' => $this->searchId,
            'occurred_at' => Carbon::createFromFormat(self::DATE_FORMAT, $this->occurredAt, 'UTC'),
            'market' => $this->market,
            'locale' => $this->locale,
            'source' => $this->source,
            'query_normalized' => $this->queryNormalized,
            'query_hash' => $this->queryHash,
            'filters' => $this->filters,
            'result_count' => $this->resultCount,
            'result_refs' => $this->resultRefs,
            'session_hash' => $this->sessionHash,
            'is_bot' => $this->isBot,
        ])->save();

        event(new SearchPerformed($this->searchId, $this->market, $this->source, $this->queryHash, $this->resultCount, $this->isBot));

        if ($this->resultCount === 0) {
            event(new ZeroResultSearchRecorded($this->searchId, $this->market, $this->source, $this->queryHash, $this->isBot));
        }
    }
}
