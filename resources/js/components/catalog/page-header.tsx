import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: ReactNode;
    eyebrow?: ReactNode;
    children?: ReactNode;
};

/** Route header with the page's single h1. */
export function PageHeader({ title, eyebrow, children }: PageHeaderProps) {
    return (
        <header className="pt-2 pb-2">
            {eyebrow ? <p className="mb-2 eyebrow">{eyebrow}</p> : null}
            <h1 className="text-[clamp(28px,3.6vw,42px)] leading-[1.05] font-black tracking-[-0.03em] text-balance text-text">
                {title}
            </h1>
            {children ? (
                <div className="mt-3 max-w-3xl text-[15px] leading-relaxed text-text-3">
                    {children}
                </div>
            ) : null}
        </header>
    );
}
