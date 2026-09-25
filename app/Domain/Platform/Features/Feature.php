<?php

namespace App\Domain\Platform\Features;

/**
 * A togglable capability, config-backed (A-17: wrapper hides the backend;
 * DB overrides / Pennant are future work, not this task).
 */
enum Feature: string
{
    case MerchantFeeds = 'merchant-feeds';
    case FeedUrlFetch = 'feed-url-fetch';
    case MatchingAutoPublish = 'matching-auto-publish';

    /**
     * Whether this flag's state may be exposed to the client (shared Inertia
     * prop). Server-only flags (fetch guards, publishing behaviour) never
     * leak their state to the browser.
     */
    public function clientVisible(): bool
    {
        return $this === self::MerchantFeeds;
    }
}
