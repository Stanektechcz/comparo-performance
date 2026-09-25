<?php

namespace App\Domain\Merchants\Queries;

use App\Models\Merchant;
use App\Models\User;

/**
 * Batched display names for merchant and member ids (one query per call,
 * no N+1). Only ids and names leave this class.
 */
final class MerchantNames
{
    /**
     * @param  list<int>  $merchantIds
     * @return array<int, string> merchant id => name
     */
    public function of(array $merchantIds): array
    {
        $merchantIds = array_values(array_unique($merchantIds));

        if ($merchantIds === []) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = Merchant::query()->whereIn('id', $merchantIds)->pluck('name', 'id')->all();

        return $names;
    }

    /**
     * Merchants ordered by name, at most `$limit`.
     *
     * @return list<array{id: int, name: string}>
     */
    public function options(int $limit): array
    {
        return array_values(Merchant::query()
            ->orderBy('name')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get(['id', 'name'])
            ->map(static fn (Merchant $merchant): array => ['id' => $merchant->id, 'name' => $merchant->name])
            ->all());
    }

    /**
     * Names of those users who are members of the merchant (others are absent).
     *
     * @param  list<int>  $userIds
     * @return array<int, string> user id => name
     */
    public function members(int $merchantId, array $userIds): array
    {
        $userIds = array_values(array_unique($userIds));

        if ($userIds === []) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = User::query()
            ->whereIn('users.id', $userIds)
            ->whereHas('merchants', static fn ($query) => $query->whereKey($merchantId))
            ->pluck('name', 'id')
            ->all();

        return $names;
    }
}
