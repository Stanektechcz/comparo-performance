import type { ReactNode } from 'react';
import type { Tone } from '@/lib/tones';
import { toneSoft } from '@/lib/tones';
import { cn } from '@/lib/utils';

type BadgeProps = {
    tone?: Tone | 'solid';
    children: ReactNode;
    className?: string;
    title?: string;
};

/** Pill badge (design-system-map §2.2). `solid` = Best value. */
export function Badge({
    tone = 'neutral',
    children,
    className,
    title,
}: BadgeProps) {
    return (
        <span
            title={title}
            className={cn(
                'inline-flex items-center gap-1 rounded-pill border px-2 py-0.5 text-[10px] leading-[1.6] font-extrabold tracking-[0.07em] whitespace-nowrap uppercase',
                tone === 'solid'
                    ? 'border-acc bg-acc text-acc-ink'
                    : toneSoft[tone],
                className,
            )}
        >
            {children}
        </span>
    );
}
