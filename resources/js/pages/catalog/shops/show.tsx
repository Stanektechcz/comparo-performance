import { BadgeCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/catalog/page-header';
import { ProductGrid } from '@/components/catalog/product-grid';
import { Section } from '@/components/comparo/section';
import { TrustBadge } from '@/components/merchants/trust-badge';
import { Money } from '@/components/money';
import { CatalogBreadcrumbs } from '@/components/navigation/catalog-breadcrumbs';
import { SeoHead } from '@/components/seo/seo-head';
import { useMarket } from '@/hooks/use-shared-props';
import { catalogUrls } from '@/lib/catalog-urls';
import { deliveryText } from '@/lib/offers';
import type { ShopShowProps } from '@/types/catalog';

function Fact({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="bg-surface-2 px-4 py-3">
            <dt className="eyebrow">{label}</dt>
            <dd className="mt-1.5 text-[13px] text-text">{children}</dd>
        </div>
    );
}

function websiteHost(url: string): string {
    try {
        return new URL(url).host;
    } catch {
        return url;
    }
}

export default function ShopShow({ seo, shop, products }: ShopShowProps) {
    const market = useMarket();

    return (
        <>
            <SeoHead seo={seo} />
            <CatalogBreadcrumbs
                items={[
                    { label: 'Home', href: catalogUrls.home() },
                    { label: 'Shops', href: catalogUrls.shops() },
                    { label: shop.name },
                ]}
            />
            <PageHeader
                title={shop.name}
                eyebrow={
                    shop.verified ? (
                        <span className="inline-flex items-center gap-1 text-ok">
                            <BadgeCheck
                                aria-hidden="true"
                                className="size-3.5"
                            />
                            Verified shop
                        </span>
                    ) : (
                        'Shop'
                    )
                }
            >
                {shop.description}
            </PageHeader>
            <div className="mt-4">
                <TrustBadge trust={shop.trust} shopName={shop.name} />
            </div>

            <Section id="shop-facts" title="Delivery and returns">
                <dl className="grid grid-cols-1 gap-px overflow-hidden rounded-card border border-line bg-line sm:grid-cols-2 lg:grid-cols-4">
                    <Fact label={`Shipping to ${market.name}`}>
                        {shop.shipping ? (
                            <>
                                <Money
                                    value={shop.shipping.cost}
                                    className="font-bold"
                                />
                                {shop.shipping.freeOver ? (
                                    <span className="block text-text-3">
                                        Free over{' '}
                                        <Money value={shop.shipping.freeOver} />
                                    </span>
                                ) : null}
                            </>
                        ) : (
                            `Does not deliver to ${market.name}`
                        )}
                    </Fact>
                    <Fact label="Delivery time">
                        <span className="num">
                            {shop.shipping
                                ? deliveryText(shop.shipping.deliveryDays)
                                : '—'}
                        </span>
                    </Fact>
                    <Fact label="Returns">
                        {shop.returnDays === null ? (
                            'Not stated'
                        ) : (
                            <span className="num">{shop.returnDays} days</span>
                        )}
                    </Fact>
                    <Fact label="Website">
                        {shop.website ? (
                            <a
                                href={shop.website}
                                target="_blank"
                                rel="nofollow noopener noreferrer"
                                className="break-all text-acc-text hover:underline"
                            >
                                {websiteHost(shop.website)}
                                <span className="sr-only">
                                    {' '}
                                    (opens in a new tab)
                                </span>
                            </a>
                        ) : (
                            'Not listed'
                        )}
                    </Fact>
                </dl>
                {shop.markets.length > 0 ? (
                    <p className="mt-3 text-[13px] text-text-3">
                        Delivers to: {shop.markets.join(', ')}
                    </p>
                ) : null}
            </Section>

            <Section
                id="shop-products"
                title={`Products at ${shop.name}`}
                description={`Lowest totals across all shops delivering to ${market.name}.`}
            >
                <ProductGrid
                    products={products}
                    emptyTitle="No products listed for this shop yet"
                />
            </Section>
        </>
    );
}
