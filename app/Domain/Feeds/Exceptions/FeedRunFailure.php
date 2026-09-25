<?php

namespace App\Domain\Feeds\Exceptions;

use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\Fetching\FeedFetchException;
use App\Domain\Feeds\Parsing\FeedParseException;
use RuntimeException;
use Throwable;

/**
 * A run-fatal pipeline outcome with a taxonomy code. The message is a fixed
 * diagnostic (code and line only) — never URLs, payload content or secrets;
 * merchants see the translation of {@see $errorCode} rendered with {@see $params}.
 */
final class FeedRunFailure extends RuntimeException
{
    /**
     * @param  array<string, string|int>  $params
     */
    public function __construct(
        public readonly FeedErrorCode $errorCode,
        public readonly array $params = [],
        public readonly ?int $lineNumber = null,
    ) {
        parent::__construct('Feed run failed: '.$errorCode->value.($lineNumber !== null ? " at line {$lineNumber}" : '').'.');
    }

    /**
     * Classify any pipeline exception. Unknown exceptions become INTERNAL_ERROR:
     * their messages may contain URLs or SQL and are never shown to merchants.
     */
    public static function from(Throwable $exception): self
    {
        return match (true) {
            $exception instanceof self => $exception,
            $exception instanceof FeedFetchException => new self($exception->errorCode, $exception->params),
            $exception instanceof FeedParseException => new self($exception->errorCode, $exception->params, $exception->lineNumber),
            default => new self(FeedErrorCode::InternalError),
        };
    }
}
