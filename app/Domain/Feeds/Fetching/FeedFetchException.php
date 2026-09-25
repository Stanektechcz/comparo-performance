<?php

namespace App\Domain\Feeds\Fetching;

use App\Domain\Feeds\FeedErrorCode;
use RuntimeException;
use Throwable;

/**
 * A run-fatal fetch failure. The message is a fixed diagnostic string: it never
 * contains the URL (which may carry tokens), resolved IP addresses, headers or
 * credentials. The HTTP client's own exception is deliberately not chained,
 * because its message includes the full request URL.
 */
final class FeedFetchException extends RuntimeException
{
    /**
     * @param  array<string, string|int>  $params
     */
    public function __construct(
        public readonly FeedErrorCode $errorCode,
        public readonly array $params = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct('Feed fetch failed: '.$errorCode->value.'.', 0, $previous);
    }
}
