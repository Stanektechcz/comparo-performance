import type { ReactNode } from 'react';
import type { Tone } from '@/lib/tones';
import { toneSoft } from '@/lib/tones';
import { cn } from '@/lib/utils';

type NoticeProps = {
    tone: Tone;
    title: string;
    children?: ReactNode;
    className?: string;
};

/** Status-toned banner: tint background, status border, readable ink. */
export function Notice({ tone, title, children, className }: NoticeProps) {
    return (
        <div
            role="note"
            className={cn(
                'rounded-card border px-4 py-3.5',
                toneSoft[tone],
                className,
            )}
        >
            <p className="flex items-center gap-2 text-sm font-extrabold">
                <span
                    aria-hidden="true"
                    className="size-1.5 shrink-0 rounded-full bg-current"
                />
                {title}
            </p>
            {children ? (
                <div className="mt-1.5 text-[13px] leading-relaxed">
                    {children}
                </div>
            ) : null}
        </div>
    );
}
