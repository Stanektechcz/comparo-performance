import { PageHeader } from '@/components/catalog/page-header';
import { Pagination } from '@/components/catalog/pagination';
import { ProductGrid } from '@/components/catalog/product-grid';
import { CatalogBreadcrumbs } from '@/components/navigation/catalog-breadcrumbs';
import { SeoHead } from '@/components/seo/seo-head';
import { useMarket } from '@/hooks/use-shared-props';
import { catalogUrls } from '@/lib/catalog-urls';
import type { ProductIndexProps } from '@/types/catalog';

export default function ProductIndex({ seo, products }: ProductIndexProps) {
    const market = useMarket();

    return (
        <>
            <SeoHead seo={seo} />
            <CatalogBreadcrumbs
                items={[
                    { label: 'Home', href: catalogUrls.home() },
                    { label: 'Products' },
                ]}
            />
            <PageHeader title="Products">
                <span className="num">{products.meta.total}</span> products.
                Lowest totals include shipping to {market.name} and the best
                working coupon.
            </PageHeader>
            <section aria-labelledby="product-list-heading" className="pt-6">
                <h2 id="product-list-heading" className="sr-only">
                    Product list, page {products.meta.currentPage}
                </h2>
                <ProductGrid products={products.data} />
                <Pagination meta={products.meta} links={products.links} />
            </section>
        </>
    );
}
