import { clicks } from '@/routes/search';
import type { SearchResultType } from '@/types/search';

/** Laravel's readable CSRF cookie (sent back as X-XSRF-TOKEN). */
function xsrfToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const cookie = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));

    return cookie
        ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length))
        : null;
}

/**
 * Fire-and-forget result-click attribution (POST /search/clicks). Uses a
 * keepalive fetch so it survives the navigation it accompanies; it never
 * awaits, never throws and never blocks the link. The server answers 204
 * whatever happens.
 */
export function reportResultClick(
    searchId: string | null,
    entityType: SearchResultType,
    entityId: number,
    position: number,
): void {
    if (searchId === null || typeof fetch === 'undefined') {
        return;
    }

    const token = xsrfToken();

    try {
        void fetch(clicks.url(), {
            method: 'POST',
            keepalive: true,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-XSRF-TOKEN': token } : {}),
            },
            body: JSON.stringify({
                search_id: searchId,
                entity_type: entityType,
                entity_id: entityId,
                position,
            }),
        }).catch(() => undefined);
    } catch {
        // Attribution is best-effort; navigation must never depend on it.
    }
}
