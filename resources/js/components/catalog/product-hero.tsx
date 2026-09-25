import { Link } from '@inertiajs/react';
import { RatingLine } from '@/components/catalog/rating-line';
import { Money } from '@/components/money';
import { useMarket } from '@/hooks/use-shared-props';
import { catalogUrls } from '@/lib/catalog-urls';
import type { OfferSummary, ProductDetail } from '@/types/catalog';

type ProductHeroProps = {
    product: ProductDetail;
    summary: OfferSummary;
    showPrice: boolean;
};

export function ProductHero({ product, summary, showPrice }: ProductHeroProps) {
    const market = useMarket();

    return (
        <div className="grid grid-cols-1 gap-6 pt-4 lg:grid-cols-[minmax(0,3fr)_minmax(280px,2fr)] lg:items-end">
            <div className="min-w-0">
                <p className="eyebrow">
                    <Link
                        href={catalogUrls.brand(product.brand.slug)}
                        className="text-acc-text hover:underline"
                    >
                        {product.brand.name}
                    </Link>
                </p>
                <h1 className="mt-2 text-[clamp(28px,3.4vw,42px)] leading-[1.05] font-black tracking-[-0.03em] text-balance text-text">
                    {product.name}
                </h1>
                <p className="mt-2 text-[13px] text-text-3">
                    {product.packLabel}
                    {product.servings ? ` · ${product.servings} servings` : ''}
                </p>
                <RatingLine rating={product.rating} className="mt-3" />
                {product.shortDescription ? (
                    <p className="mt-4 max-w-2xl text-base leading-relaxed text-text-3">
                        {product.shortDescription}
                    </p>
                ) : null}
            </div>
            {showPrice ? (
                <div className="rounded-panel border border-line bg-surface p-5">
                    <p className="eyebrow">
                        Best total incl. shipping to {market.name}
                    </p>
                    {summary.lowestTotal ? (
                        <p className="mt-2">
                            <Money
                                value={summary.lowestTotal}
                                className="text-[34px] leading-none font-bold text-acc-text"
                            />
                        </p>
                    ) : (
                        <p className="mt-2 text-sm text-text-3">
                            No shop currently ships this product to{' '}
                            {market.name}.
                        </p>
                    )}
                    <p className="mt-3 text-xs text-text-3">
                        Product + shipping − best working coupon.
                    </p>
                </div>
            ) : null}
        </div>
    );
}
