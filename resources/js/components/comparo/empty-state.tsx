import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type EmptyStateProps = {
    title: string;
    children?: ReactNode;
    className?: string;
};

export function EmptyState({ title, children, className }: EmptyStateProps) {
    return (
        <div
            className={cn(
                'rounded-card border border-line bg-surface p-6',
                className,
            )}
        >
            <p className="text-base font-extrabold text-text">{title}</p>
            {children ? (
                <div className="mt-1.5 text-sm text-text-3">{children}</div>
            ) : null}
        </div>
    );
}
