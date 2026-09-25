/**
 * Per-viewer recent searches, kept ONLY in this browser's localStorage and
 * never sent to the server (docs/architecture/phase-3-search.md §4).
 * Storage can be unavailable (private mode, blocked site data, SSR): every
 * access is guarded and failures degrade to "no recent searches".
 */
const STORAGE_KEY = 'comparo:recent-searches';
const MAX_RECENT = 8;
const MAX_LENGTH = 200;
const CHANGE_EVENT = 'comparo:recent-searches';

function notify(): void {
    try {
        window.dispatchEvent(new Event(CHANGE_EVENT));
    } catch {
        // No window (SSR): nobody is listening.
    }
}

/** useSyncExternalStore subscription: this tab's writes and other tabs'. */
export function subscribeRecentSearches(onChange: () => void): () => void {
    if (typeof window === 'undefined') {
        return () => undefined;
    }

    window.addEventListener(CHANGE_EVENT, onChange);
    window.addEventListener('storage', onChange);

    return () => {
        window.removeEventListener(CHANGE_EVENT, onChange);
        window.removeEventListener('storage', onChange);
    };
}

/** The raw stored value: a string, so it is a stable snapshot. */
export function recentSearchesSnapshot(): string {
    try {
        return storage()?.getItem(STORAGE_KEY) ?? '';
    } catch {
        return '';
    }
}

export function parseRecentSearches(raw: string): string[] {
    try {
        const parsed: unknown = raw ? JSON.parse(raw) : [];

        return Array.isArray(parsed)
            ? parsed
                  .filter((item): item is string => typeof item === 'string')
                  .slice(0, MAX_RECENT)
            : [];
    } catch {
        return [];
    }
}

function storage(): Storage | null {
    try {
        return typeof window === 'undefined' ? null : window.localStorage;
    } catch {
        return null;
    }
}

export function readRecentSearches(): string[] {
    return parseRecentSearches(recentSearchesSnapshot());
}

export function rememberSearch(query: string): string[] {
    const text = query.trim().slice(0, MAX_LENGTH);

    if (text.length < 2) {
        return readRecentSearches();
    }

    const next = [
        text,
        ...readRecentSearches().filter(
            (item) => item.toLowerCase() !== text.toLowerCase(),
        ),
    ].slice(0, MAX_RECENT);

    try {
        storage()?.setItem(STORAGE_KEY, JSON.stringify(next));
        notify();
    } catch {
        // Storage full or blocked: recent searches are a convenience only.
    }

    return next;
}

export function clearRecentSearches(): void {
    try {
        storage()?.removeItem(STORAGE_KEY);
        notify();
    } catch {
        // Nothing to clear when storage is unavailable.
    }
}
