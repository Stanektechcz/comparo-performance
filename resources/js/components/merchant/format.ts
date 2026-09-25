const MS_PER_SECOND = 1000;
const SECONDS_PER_MINUTE = 60;
const MINUTES_PER_HOUR = 60;
const MINUTES_PER_DAY = 1440;
const BYTES_PER_KB = 1024;

/** "2 min 5 s" style run durations. */
export function formatDuration(ms: number | null): string | null {
    if (ms === null) {
        return null;
    }

    const seconds = Math.round(ms / MS_PER_SECOND);

    if (seconds < SECONDS_PER_MINUTE) {
        return `${seconds} s`;
    }

    const minutes = Math.floor(seconds / SECONDS_PER_MINUTE);
    const rest = seconds % SECONDS_PER_MINUTE;

    return rest === 0 ? `${minutes} min` : `${minutes} min ${rest} s`;
}

export function formatSchedule(minutes: number | null): string {
    if (minutes === null) {
        return 'Manual runs only';
    }

    if (minutes === MINUTES_PER_DAY) {
        return 'Once a day';
    }

    if (minutes % MINUTES_PER_HOUR === 0) {
        const hours = minutes / MINUTES_PER_HOUR;

        return hours === 1 ? 'Every hour' : `Every ${hours} hours`;
    }

    return `Every ${minutes} minutes`;
}

export function formatBytes(bytes: number | null): string | null {
    if (bytes === null) {
        return null;
    }

    if (bytes < BYTES_PER_KB) {
        return `${bytes} B`;
    }

    const kb = bytes / BYTES_PER_KB;

    return kb < BYTES_PER_KB
        ? `${kb.toFixed(1)} KB`
        : `${(kb / BYTES_PER_KB).toFixed(1)} MB`;
}
