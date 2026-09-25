<?php

namespace App\Domain\Catalog;

/**
 * Review state of a brand alias. Only approved aliases are used by the matcher.
 */
enum BrandAliasStatus: string
{
    case Approved = 'approved';
    case Suggested = 'suggested';
    case Rejected = 'rejected';

    public function isUsableForMatching(): bool
    {
        return $this === self::Approved;
    }
}
