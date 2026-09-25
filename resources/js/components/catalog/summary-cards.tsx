import { Link } from '@inertiajs/react';
import { BadgeCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { TrustBadge } from '@/components/merchants/trust-badge';
import { useMarket } from '@/hooks/use-shared-props';
import { catalogUrls } from '@/lib/catalog-urls';
import { pluralize } from '@/lib/format';
import type {
    BrandSummary,
    CategorySummary,
    ShopSummary,
} from '@/types/catalog';

function CardShell({ children }: { children: ReactNode }) {
    return (
        <article className="flex h-full flex-col gap-2 rounded-card border border-line bg-surface p-4 transition-colors hover:border-line-2">
            {children}
        </article>
    );
}

function CountLine({ count, noun }: { count: number; noun: string }) {
    return (
        <p className="mt-auto pt-2 num text-[11px] text-text-4">
            {count} {pluralize(count, noun)}
        </p>
    );
}

export function CategoryCard({ category }: { category: CategorySummary }) {
    return (
        <CardShell>
            <h3 className="text-base font-extrabold">
                <Link
                    href={catalogUrls.category(category.slug)}
                    className="text-text hover:text-acc-text"
                >
                    {category.name}
                </Link>
            </h3>
            {category.description ? (
                <p className="text-[13px] text-text-3">
                    {category.description}
                </p>
            ) : null}
            <CountLine count={category.productCount} noun="product" />
        </CardShell>
    );
}

export function BrandCard({ brand }: { brand: BrandSummary }) {
    return (
        <CardShell>
            <h3 className="text-base font-extrabold">
                <Link
                    href={catalogUrls.brand(brand.slug)}
                    className="text-text hover:text-acc-text"
                >
                    {brand.name}
                </Link>
            </h3>
            {brand.originCountry ? (
                <p className="text-[13px] text-text-3">
                    From {brand.originCountry}
                </p>
            ) : null}
            <CountLine count={brand.productCount} noun="product" />
        </CardShell>
    );
}

export function ShopCard({ shop }: { shop: ShopSummary }) {
    const market = useMarket();

    return (
        <CardShell>
            <h3 className="flex flex-wrap items-center gap-1.5 text-base font-extrabold">
                <Link
                    href={catalogUrls.shop(shop.slug)}
                    className="text-text hover:text-acc-text"
                >
                    {shop.name}
                </Link>
                {shop.verified ? (
                    <span className="inline-flex items-center gap-1 text-[11px] font-bold text-ok">
                        <BadgeCheck aria-hidden="true" className="size-3.5" />
                        Verified
                    </span>
                ) : null}
            </h3>
            <div>
                <TrustBadge trust={shop.trust} shopName={shop.name} />
            </div>
            <p className="text-[13px] text-text-3">
                {shop.shipsToMarket
                    ? `Delivers to ${market.name}`
                    : `Does not deliver to ${market.name}`}
            </p>
            <CountLine count={shop.offerCount} noun="offer" />
        </CardShell>
    );
}

export function CardGrid({ children }: { children: ReactNode }) {
    return (
        <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {children}
        </ul>
    );
}
