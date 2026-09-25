import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type SectionProps = {
    id: string;
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
};

/** A page section whose h2 labels the region. */
export function Section({
    id,
    title,
    description,
    actions,
    children,
    className,
}: SectionProps) {
    const headingId = `${id}-heading`;

    return (
        <section
            id={id}
            aria-labelledby={headingId}
            className={cn('pt-10', className)}
        >
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div className="min-w-0">
                    <h2
                        id={headingId}
                        className="text-[22px] leading-[1.1] font-black tracking-[-0.02em] text-text"
                    >
                        {title}
                    </h2>
                    {description ? (
                        <div className="mt-1.5 text-sm text-text-3">
                            {description}
                        </div>
                    ) : null}
                </div>
                {actions ? (
                    <div className="flex flex-wrap items-center gap-2">
                        {actions}
                    </div>
                ) : null}
            </div>
            {children}
        </section>
    );
}
