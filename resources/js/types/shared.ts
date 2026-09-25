import type { Auth } from '@/types/auth';
import type { Market } from '@/types/catalog';

/**
 * Shared Inertia props every public catalogue page can rely on
 * (HandleInertiaRequests). `auth.user` is null for guests.
 */
export type PublicSharedProps = {
    name: string;
    auth: Auth;
    market: Market;
};
