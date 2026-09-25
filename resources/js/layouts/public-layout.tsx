import type { ReactNode } from 'react';
import { SiteFooter } from '@/components/navigation/site-footer';
import { SiteHeader } from '@/components/navigation/site-header';

/** Shell for the public catalogue: home and every catalog/* page. */
export default function PublicLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-dvh flex-col bg-bg text-text">
            <a
                href="#main"
                className="sr-only rounded-btn bg-acc px-4 py-2 font-bold text-acc-ink focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50"
            >
                Skip to content
            </a>
            <SiteHeader />
            <main
                id="main"
                className="mx-auto w-full max-w-[1440px] flex-1 px-4 pt-6 pb-10 sm:px-6"
            >
                {children}
            </main>
            <SiteFooter />
        </div>
    );
}
