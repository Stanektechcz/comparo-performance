import { PageHeader } from '@/components/catalog/page-header';
import { CardGrid, ShopCard } from '@/components/catalog/summary-cards';
import { EmptyState } from '@/components/comparo/empty-state';
import { CatalogBreadcrumbs } from '@/components/navigation/catalog-breadcrumbs';
import { SeoHead } from '@/components/seo/seo-head';
import { useMarket } from '@/hooks/use-shared-props';
import { catalogUrls } from '@/lib/catalog-urls';
import type { ShopIndexProps } from '@/types/catalog';

export default function ShopIndex({ seo, shops }: ShopIndexProps) {
    const market = useMarket();

    return (
        <>
            <SeoHead seo={seo} />
            <CatalogBreadcrumbs
                items={[
                    { label: 'Home', href: catalogUrls.home() },
                    { label: 'Shops' },
                ]}
            />
            <PageHeader title="Shops">
                Every shop we compare, with its trust level and whether it
                delivers to {market.name}. Trust never depends on commission or
                paid plans.
            </PageHeader>
            <section aria-labelledby="shop-list-heading" className="pt-6">
                <h2 id="shop-list-heading" className="sr-only">
                    Shop list
                </h2>
                {shops.length === 0 ? (
                    <EmptyState title="No shops published yet" />
                ) : (
                    <CardGrid>
                        {shops.map((shop) => (
                            <li key={shop.slug}>
                                <ShopCard shop={shop} />
                            </li>
                        ))}
                    </CardGrid>
                )}
            </section>
        </>
    );
}
