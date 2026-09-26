<?php

namespace App\Domain\Search\Listeners;

use App\Domain\Compliance\Events\ComplianceRuleChanged;
use App\Domain\Platform\Cache\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Header suggestions are cached for 60 s per market and prefix
 * (SuggestController). A compliance change must not leave a newly blocked
 * product in that cache (invariant 2), so every change rotates the version
 * token that is part of each suggest cache key: all cached suggestions
 * become unreachable at once, on every cache store (no tags required).
 *
 * Synchronous on purpose (the event is dispatched after commit; one cache
 * write). Registered in AppServiceProvider.
 */
final readonly class InvalidateCachedSuggestions
{
    public function handle(ComplianceRuleChanged $event): void
    {
        Cache::forever(CacheKeys::searchSuggestVersion(), (string) Str::ulid());
    }

    /**
     * The version token to put into CacheKeys::searchSuggest ('0' until the
     * first compliance change).
     */
    public static function currentVersion(): string
    {
        return (string) Cache::get(CacheKeys::searchSuggestVersion(), '0');
    }
}
