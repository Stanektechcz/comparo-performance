<?php

namespace App\Domain\Search\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A recorded search found nothing visible in its market (result_count = 0):
 * unmet demand. Ids and scalars only.
 */
final readonly class ZeroResultSearchRecorded implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $searchId,
        public string $market,
        public string $source,
        public string $queryHash,
        public bool $isBot,
    ) {}
}
