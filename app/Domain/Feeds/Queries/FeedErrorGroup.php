<?php

namespace App\Domain\Feeds\Queries;

use App\Domain\Feeds\FeedErrorSeverity;

/**
 * The errors of one run with one code and severity. `count` is the number of
 * stored rows; `capped` means storage stopped at `comparo.feeds.max_errors_per_code`
 * (the run metrics hold the true totals).
 */
final readonly class FeedErrorGroup
{
    /**
     * @param  list<array{row_number: int|null, field: string|null, params: array<string, mixed>, merchant_sku: string|null}>  $samples
     */
    public function __construct(
        public string $code,
        public FeedErrorSeverity $severity,
        public string $messageKey,
        public int $count,
        public bool $capped,
        public array $samples,
    ) {}
}
