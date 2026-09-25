<?php

namespace App\Http\Presenters\Admin;

use App\Domain\Accounts\Queries\UserNames;
use App\Domain\Merchants\Queries\MerchantNames;

/**
 * Batched display names for ids the matching queries return (one query per
 * kind per page, no N+1; the reads live in {@see MerchantNames} and
 * {@see UserNames}). Only names leave this class.
 */
final class ReferenceNames
{
    /** Upper bound on the merchant filter options. */
    public const int MERCHANT_OPTIONS_MAX = 500;

    public function __construct(
        private readonly MerchantNames $merchantNames,
        private readonly UserNames $userNames,
    ) {}

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function merchants(array $ids): array
    {
        return $this->merchantNames->of($ids);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function users(array $ids): array
    {
        return $this->userNames->of($ids);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function merchantOptions(): array
    {
        return $this->merchantNames->options(self::MERCHANT_OPTIONS_MAX);
    }
}
