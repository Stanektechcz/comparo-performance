<?php

namespace App\Domain\Feeds\Events;

use App\Domain\Feeds\FeedRunOutcome;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A feed run completed. `outcome` is a {@see FeedRunOutcome} value.
 */
final readonly class FeedImported implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $runId,
        public int $sourceId,
        public int $merchantId,
        public string $outcome,
    ) {}
}
