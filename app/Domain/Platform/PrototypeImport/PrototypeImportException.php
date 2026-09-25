<?php

namespace App\Domain\Platform\PrototypeImport;

use RuntimeException;

/**
 * The prototype snapshot is missing, unreadable or structurally invalid.
 */
final class PrototypeImportException extends RuntimeException
{
    public static function unreadable(string $path): self
    {
        return new self("Prototype snapshot [{$path}] does not exist or cannot be read.");
    }

    public static function invalid(string $path, string $reason): self
    {
        return new self("Prototype snapshot [{$path}] is invalid: {$reason}");
    }

    public static function missingReference(string $entity, string $key): self
    {
        return new self("Prototype import references unknown {$entity} [{$key}]. Import the earlier stages first.");
    }
}
