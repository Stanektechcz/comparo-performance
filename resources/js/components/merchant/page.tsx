import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { buttonStyles } from '@/components/comparo/button-styles';
import { cn } from '@/lib/utils';

/** Page frame of the merchant portal: 16 px gutters, no horizontal page scroll. */
export function MerchantPage({
    children,
    back,
    className,
}: {
    children: ReactNode;
    back?: { href: NonNullable<InertiaLinkProps['href']>; label: string };
    className?: string;
}) {
    return (
        <div
            className={cn(
                'mx-auto w-full max-w-[1400px] min-w-0 px-4 py-6 pb-12 sm:px-6',
                className,
            )}
        >
            {back ? (
                <Link href={back.href} className={buttonStyles.tertiary}>
                    <span aria-hidden="true">←</span> {back.label}
                </Link>
            ) : null}
            {children}
        </div>
    );
}

/** One labelled figure (metric tiles, feed facts). */
export function StatTile({
    label,
    value,
    hint,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-1 rounded-card border border-line bg-surface px-4 py-3">
            <dt className="text-[11px] font-extrabold tracking-[0.08em] text-text-4 uppercase">
                {label}
            </dt>
            <dd className="num text-[22px] leading-tight font-bold break-words text-text">
                {value}
            </dd>
            {hint ? <dd className="text-xs text-text-3">{hint}</dd> : null}
        </div>
    );
}
