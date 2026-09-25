import { Link } from '@inertiajs/react';
import { catalogUrls } from '@/lib/catalog-urls';

export function Wordmark() {
    return (
        <Link
            href={catalogUrls.home()}
            className="flex shrink-0 items-baseline gap-2"
            aria-label="Comparo Performance — home"
        >
            <span className="text-xl leading-none font-black tracking-[-0.02em] text-text uppercase">
                Comparo
            </span>
            <span className="hidden num text-[10px] leading-none font-bold tracking-[0.14em] text-acc-text uppercase min-[380px]:inline">
                Performance
            </span>
        </Link>
    );
}
