import { AccountLinks } from '@/components/navigation/account-links';
import { MarketSelect } from '@/components/navigation/market-select';
import { MobileMenu } from '@/components/navigation/mobile-menu';
import { PrimaryNav } from '@/components/navigation/primary-nav';
import { ThemeMenu } from '@/components/navigation/theme-menu';
import { Wordmark } from '@/components/navigation/wordmark';

export function SiteHeader() {
    return (
        <header className="sticky top-0 z-40 border-b border-line bg-bg-blur backdrop-blur-md">
            <div className="mx-auto flex max-w-[1440px] items-center gap-4 px-4 py-2.5 sm:px-6">
                <Wordmark />
                <PrimaryNav className="hidden md:block" />
                <div className="ml-auto flex items-center gap-2">
                    <MarketSelect
                        className="hidden lg:flex"
                        labelClassName="sr-only xl:not-sr-only"
                    />
                    <ThemeMenu />
                    <AccountLinks className="hidden md:flex" />
                    <div className="md:hidden">
                        <MobileMenu />
                    </div>
                </div>
            </div>
            <div className="hidden border-t border-line-soft bg-surface-sub md:block lg:hidden">
                <div className="mx-auto flex max-w-[1440px] justify-end px-6 py-1.5">
                    <MarketSelect />
                </div>
            </div>
        </header>
    );
}
