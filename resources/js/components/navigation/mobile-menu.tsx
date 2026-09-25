import { Menu } from 'lucide-react';
import { useState } from 'react';
import { buttonStyles } from '@/components/comparo/button-styles';
import { AccountLinks } from '@/components/navigation/account-links';
import { MarketSelect } from '@/components/navigation/market-select';
import { PrimaryNav } from '@/components/navigation/primary-nav';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

/** Below md the primary nav, market and account links live in a sheet. */
export function MobileMenu() {
    const [open, setOpen] = useState(false);
    const close = () => setOpen(false);

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger
                className={buttonStyles.icon}
                aria-label="Open menu"
                title="Open menu"
            >
                <Menu aria-hidden="true" className="size-4" />
            </SheetTrigger>
            <SheetContent
                side="right"
                className="w-[min(320px,100vw)] border-line bg-surface p-5 text-text"
            >
                <SheetTitle className="text-lg font-black">Menu</SheetTitle>
                <SheetDescription className="sr-only">
                    Catalogue navigation, delivery market and account links.
                </SheetDescription>
                <PrimaryNav orientation="vertical" onNavigate={close} />
                <MarketSelect className="flex-col items-stretch" />
                <AccountLinks onNavigate={close} className="flex-wrap" />
            </SheetContent>
        </Sheet>
    );
}
