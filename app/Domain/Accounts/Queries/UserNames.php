<?php

namespace App\Domain\Accounts\Queries;

use App\Models\User;

/**
 * Batched display names for user ids (one query, no N+1). Only names leave
 * this class — never e-mail addresses or roles.
 */
final class UserNames
{
    /**
     * @param  list<int>  $userIds
     * @return array<int, string> user id => name
     */
    public function of(array $userIds): array
    {
        $userIds = array_values(array_unique($userIds));

        if ($userIds === []) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = User::query()->whereIn('id', $userIds)->pluck('name', 'id')->all();

        return $names;
    }
}
