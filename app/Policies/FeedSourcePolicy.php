<?php

namespace App\Policies;

use App\Models\FeedSource;
use App\Models\User;

/**
 * Merchant feed access (docs/architecture/phase-2-feeds-matching.md §8).
 *
 * The HTTP layer resolves a source through MerchantFeedSources scoped to the
 * active merchant first (a foreign id is a 404); these checks then gate what
 * the member's role may do: every member may view, owners and managers
 * configure and run feeds, only the owner changes credentials. Analysts are
 * read-only. Staff never act through the merchant portal.
 */
class FeedSourcePolicy
{
    public function view(User $user, FeedSource $source): bool
    {
        return $user->isMemberOf($source->merchant_id);
    }

    /**
     * Create a feed for the given merchant (the active MerchantContext merchant).
     */
    public function create(User $user, int $merchantId): bool
    {
        return (bool) $user->merchantRole($merchantId)?->canManageFeeds();
    }

    /**
     * Settings, field mapping, pause/resume, manual runs, uploads and cancelling runs.
     */
    public function update(User $user, FeedSource $source): bool
    {
        return (bool) $user->merchantRole($source->merchant_id)?->canManageFeeds();
    }

    public function run(User $user, FeedSource $source): bool
    {
        return $this->update($user, $source);
    }

    public function manageCredentials(User $user, FeedSource $source): bool
    {
        return (bool) $user->merchantRole($source->merchant_id)?->canManageFeedCredentials();
    }
}
