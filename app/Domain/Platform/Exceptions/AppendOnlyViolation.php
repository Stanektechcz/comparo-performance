<?php

namespace App\Domain\Platform\Exceptions;

use LogicException;

/**
 * Raised when code tries to update or delete a row of an append-only history.
 */
final class AppendOnlyViolation extends LogicException
{
    public static function for(string $model, string $operation): self
    {
        return new self("[{$model}] is append-only; {$operation} is not allowed. Write a new (correcting) record instead.");
    }
}
