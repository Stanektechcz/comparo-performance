<?php

namespace App\Domain\Search\Analytics;

use App\Domain\Search\SearchSource;
use DateTimeImmutable;

/**
 * What the HTTP layer hands to RecordSearch: the raw text (redacted there,
 * never stored raw), the allow-listed search criteria and the displayed
 * result references. Carries no IP address and no user id by design; the
 * session id and user agent are only used to derive `session_hash` and
 * `is_bot` and are never stored.
 */
final readonly class SearchRecordInput
{
    /**
     * @param  array<string, mixed>  $filters  criteria; RecordSearch keeps allow-listed keys only
     * @param  list<array{type: string, id: int}>  $resultRefs  the displayed results, position = index + 1
     */
    public function __construct(
        public SearchSource $source,
        public string $market,
        public string $locale,
        public string $rawQuery,
        public array $filters,
        public int $resultCount,
        public array $resultRefs,
        public ?string $sessionId,
        public ?string $userAgent,
        public bool $isPrefetch,
        public DateTimeImmutable $occurredAt,
    ) {}
}
