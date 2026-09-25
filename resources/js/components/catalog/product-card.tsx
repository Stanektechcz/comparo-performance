import { Link } from '@inertiajs/react';
import { RatingLine } from '@/components/catalog/rating-line';
import { Money } from '@/components/money';
import { useMarket } from '@/hooks/use-shared-props';
import { catalogUrls } from '@/lib/catalog-urls';
import { pluralize } from '@/lib/format';
import type { ProductSummary } from '@/types/catalog';

/** Product teaser; the whole card title is the link. */
export function ProductCard({ product }: { product: ProductSummary }) {
    const market = useMarket();

    return (
        <article className="flex h-full flex-col rounded-card border border-line bg-surface p-4 transition-colors hover:border-line-2">
            <p className="num text-[10px] font-semibold tracking-[0.08em] text-text-3 uppercase">
                {product.brand.name}
            </p>
            <h3 className="mt-1 text-[15px] leading-snug font-bold">
                <Link
                    href={catalogUrls.product(product.slug)}
                    className="text-text hover:text-acc-text"
                >
                    {product.name}
                </Link>
            </h3>
            <p className="mt-1 text-xs text-text-3">
                {product.category.name} · {product.packLabel}
            </p>
            <RatingLine rating={product.rating} className="mt-2" />
            <div className="mt-auto pt-4">
                {product.lowestTotal ? (
                    <p className="text-xs text-text-3">
                        From{' '}
                        <Money
                            value={product.lowestTotal}
                            className="text-base font-bold text-acc-text"
                        />{' '}
                        incl. shipping to {market.name}
                    </p>
                ) : (
                    <p className="text-xs text-text-3">
                        No offers for {market.name} yet
                    </p>
                )}
                <p className="mt-1 num text-[11px] text-text-4">
                    {product.offerCount}{' '}
                    {pluralize(product.offerCount, 'offer')}
                </p>
            </div>
        </article>
    );
}
