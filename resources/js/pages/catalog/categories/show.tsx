import { PageHeader } from '@/components/catalog/page-header';
import { ProductGrid } from '@/components/catalog/product-grid';
import { Section } from '@/components/comparo/section';
import { CatalogBreadcrumbs } from '@/components/navigation/catalog-breadcrumbs';
import { SeoHead } from '@/components/seo/seo-head';
import { useMarket } from '@/hooks/use-shared-props';
import { catalogUrls } from '@/lib/catalog-urls';
import type { CategoryShowProps } from '@/types/catalog';

export default function CategoryShow({
    seo,
    category,
    products,
}: CategoryShowProps) {
    const market = useMarket();

    return (
        <>
            <SeoHead seo={seo} />
            <CatalogBreadcrumbs
                items={[
                    { label: 'Home', href: catalogUrls.home() },
                    { label: 'Categories', href: catalogUrls.categories() },
                    { label: category.name },
                ]}
            />
            <PageHeader title={category.name}>
                {category.description}
            </PageHeader>
            <Section
                id="category-products"
                title={`${category.name} products`}
                description={`Lowest totals include shipping to ${market.name}.`}
            >
                <ProductGrid
                    products={products}
                    emptyTitle="No products in this category yet"
                />
            </Section>
        </>
    );
}
