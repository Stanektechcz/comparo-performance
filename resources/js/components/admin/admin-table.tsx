import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export const headerCell =
    'px-3 py-3 text-left align-bottom text-[10px] font-extrabold tracking-[0.11em] whitespace-nowrap text-text-4 uppercase first:pl-5 last:pr-5';

export const bodyCell =
    'px-3 py-3.5 align-top text-[13px] text-text-2 first:pl-5 last:pr-5';

type AdminTableProps = {
    caption: string;
    columns: ReactNode[];
    children: ReactNode;
    minWidth?: string;
    className?: string;
};

/**
 * Dense staff table (design-system-map §2.4 pattern A): a real <table> whose
 * card scrolls horizontally on narrow screens instead of the page. The
 * scroll region is focusable so keyboard users can scroll it.
 */
export function AdminTable({
    caption,
    columns,
    children,
    minWidth = 'min-w-[860px]',
    className,
}: AdminTableProps) {
    return (
        <div
            role="region"
            aria-label={caption}
            tabIndex={0}
            className={cn(
                'max-w-full overflow-x-auto rounded-panel border border-line bg-surface',
                className,
            )}
        >
            <table className={cn('w-full border-collapse text-left', minWidth)}>
                <caption className="sr-only">{caption}</caption>
                <thead className="border-b border-line">
                    <tr>
                        {columns.map((column, index) => (
                            <th key={index} scope="col" className={headerCell}>
                                {column}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>{children}</tbody>
            </table>
        </div>
    );
}

export function AdminRow({ children }: { children: ReactNode }) {
    return (
        <tr className="border-b border-line-soft last:border-b-0">
            {children}
        </tr>
    );
}

/** Missing values are a dash, announced as "not provided". */
export function Missing() {
    return (
        <span className="text-text-4">
            <span aria-hidden="true">—</span>
            <span className="sr-only">not provided</span>
        </span>
    );
}

export function Fact({ value }: { value: string | null | undefined }) {
    return value ? <>{value}</> : <Missing />;
}

const dateTimeFormatter = new Intl.DateTimeFormat('en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'UTC',
});

/** UTC so the server render and the hydrated client render agree. */
export function DateTime({ iso }: { iso: string | null }) {
    if (!iso) {
        return <Missing />;
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return <>{iso}</>;
    }

    return (
        <time dateTime={iso} className="num whitespace-nowrap">
            {dateTimeFormatter.format(date)} UTC
        </time>
    );
}
