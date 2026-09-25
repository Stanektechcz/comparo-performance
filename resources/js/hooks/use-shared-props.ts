import { usePage } from '@inertiajs/react';
import type { Market } from '@/types/catalog';
import type { PublicSharedProps } from '@/types/shared';

/**
 * Used only when a response arrives without the shared `market` prop
 * (for example an error page rendered before the market middleware ran).
 */
const FALLBACK_MARKET: Market = {
    code: 'DE',
    name: 'Germany',
    currency: 'EUR',
    locale: 'de-DE',
    options: [],
};

/** Typed accessor for the shared props of public pages. */
export function useSharedProps(): PublicSharedProps {
    const { props } = usePage();

    return {
        name: props.name,
        auth: { user: props.auth?.user ?? null },
        market: props.market ?? FALLBACK_MARKET,
    };
}

export function useMarket(): Market {
    return useSharedProps().market;
}
