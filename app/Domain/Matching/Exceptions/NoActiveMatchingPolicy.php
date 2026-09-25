<?php

namespace App\Domain\Matching\Exceptions;

use RuntimeException;

/**
 * matching_policies has no active row: nothing may be matched until staff
 * activate a policy (the seed migration activates `prototype-v1`).
 */
final class NoActiveMatchingPolicy extends RuntimeException
{
    public static function make(): self
    {
        return new self('No active matching policy: activate a matching_policies row before matching listings.');
    }
}
