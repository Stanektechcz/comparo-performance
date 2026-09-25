<?php

namespace App\Domain\Matching\Engine;

/**
 * What happens to a match: published automatically, queued for confirmation,
 * or left unmatched. Cut-offs come from {@see MatchingPolicy::bucketFor()}.
 */
enum MatchBucket: string
{
    case Auto = 'auto';
    case Confirm = 'confirm';
    case Unmatched = 'unmatched';
}
