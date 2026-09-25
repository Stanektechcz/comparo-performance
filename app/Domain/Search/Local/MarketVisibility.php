<?php

namespace App\Domain\Search\Local;

use Closure;

/**
 * Market/compliance predicates for LocalQueryEvaluator. A predicate decides
 * whether an entry may appear at all in a market; it runs after scoring and
 * before filters, facets and counts, so a hidden product never reaches a
 * count, a facet or a page (invariant 2, A-21).
 */
final class MarketVisibility
{
    /**
     * The default: a product is visible only when it has data for the market
     * (documents carry active markets only) and is not blocked there;
     * `unknown` products stay visible without purchase data. Other types are
     * always visible.
     *
     * @return Closure(SearchableEntry, string): bool
     */
    public static function excludeBlocked(): Closure
    {
        return static function (SearchableEntry $entry, string $market): bool {
            if ($entry->type !== SearchableType::Product) {
                return true;
            }

            $state = $entry->attributes->market($market);

            return $state !== null && ! $state->compliance->isBlocked();
        };
    }

    /**
     * Everything visible: only for parity checks against the prototype, which
     * does not filter search results by market.
     *
     * @return Closure(SearchableEntry, string): bool
     */
    public static function everything(): Closure
    {
        return static fn (SearchableEntry $entry, string $market): bool => true;
    }
}
