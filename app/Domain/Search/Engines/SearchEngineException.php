<?php

namespace App\Domain\Search\Engines;

use RuntimeException;

/**
 * A search engine operation that did not complete (failed or timed-out
 * task, mismatched document). Messages never contain credentials.
 */
final class SearchEngineException extends RuntimeException
{
    public static function taskFailed(string $operation, string $index, string $code, string $message): self
    {
        return new self("Search engine task [{$operation}] on [{$index}] failed ({$code}): {$message}");
    }

    public static function wrongIndex(string $documentId, string $entity, string $index): self
    {
        return new self("Document [{$documentId}] of type [{$entity}] does not belong in index [{$index}].");
    }
}
