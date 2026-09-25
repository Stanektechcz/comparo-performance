<?php

namespace App\Domain\Feeds\Events;

use App\Domain\Feeds\FeedErrorCode;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A feed run failed. `code` is a {@see FeedErrorCode} value.
 */
final readonly class FeedFailed implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $runId,
        public int $sourceId,
        public int $merchantId,
        public string $code,
    ) {}
}
