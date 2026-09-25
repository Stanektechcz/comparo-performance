<?php

namespace App\Http\Presenters\Admin;

use App\Models\Merchant;
use App\Models\User;

/**
 * Batched display names for ids the matching queries return (one query per
 * kind per page, no N+1). Only names leave this class.
 */
final class ReferenceNames
{
    /** Upper bound on the merchant filter options. */
    public const int MERCHANT_OPTIONS_MAX = 500;

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function merchants(array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = Merchant::query()->whereIn('id', $ids)->pluck('name', 'id')->all();

        return $names;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function users(array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = User::query()->whereIn('id', $ids)->pluck('name', 'id')->all();

        return $names;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function merchantOptions(): array
    {
        return array_values(Merchant::query()
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::MERCHANT_OPTIONS_MAX)
            ->get(['id', 'name'])
            ->map(static fn (Merchant $merchant): array => ['id' => $merchant->id, 'name' => $merchant->name])
            ->all());
    }
}
