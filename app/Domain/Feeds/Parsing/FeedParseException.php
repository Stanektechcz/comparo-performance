<?php

namespace App\Domain\Feeds\Parsing;

use App\Domain\Feeds\FeedErrorCode;
use RuntimeException;

/**
 * A run-fatal parsing failure. The message is diagnostic only (code + line) and
 * never contains file paths or feed content; merchants see the translated
 * message for {@see $errorCode} rendered with {@see $params}.
 */
final class FeedParseException extends RuntimeException
{
    /**
     * @param  array<string, string|int>  $params
     */
    public function __construct(
        public readonly FeedErrorCode $errorCode,
        public readonly array $params = [],
        public readonly ?int $lineNumber = null,
    ) {
        parent::__construct(
            'Feed parsing failed: '.$errorCode->value.($lineNumber !== null ? " at line {$lineNumber}" : '').'.'
        );
    }
}
