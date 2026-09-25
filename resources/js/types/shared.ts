import type { Auth } from '@/types/auth';
import type { Market } from '@/types/catalog';

/**
 * Client-visible feature flags (App\Domain\Platform\Features\Feature::clientVisible()).
 */
export type FeatureFlags = {
    'merchant-feeds': boolean;
};

export type MerchantRole = 'owner' | 'manager' | 'analyst';

export type MerchantMembership = {
    id: number;
    slug: string;
    name: string;
    role: MerchantRole;
};

/**
 * The active merchant for the request plus every merchant the user belongs
 * to. Only present on `/merchant/*` routes (ResolveMerchantContext); null
 * everywhere else.
 */
export type MerchantContext = {
    active: MerchantMembership;
    available: MerchantMembership[];
};

/**
 * Shared Inertia props every public catalogue page can rely on
 * (HandleInertiaRequests). `auth.user` is null for guests.
 */
export type PublicSharedProps = {
    name: string;
    auth: Auth;
    market: Market;
    features?: FeatureFlags;
    merchantContext?: MerchantContext | null;
};
