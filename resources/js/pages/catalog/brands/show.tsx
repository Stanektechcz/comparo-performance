import { PageHeader } from '@/components/catalog/page-header';
import { ProductGrid } from '@/components/catalog/product-grid';
import { Section } from '@/components/comparo/section';
import { CatalogBreadcrumbs } from '@/components/navigation/catalog-breadcrumbs';
import { SeoHead } from '@/components/seo/seo-head';
import { catalogUrls } from '@/lib/catalog-urls';
import type { BrandShowProps } from '@/types/catalog';

export default function BrandShow({ seo, brand, products }: BrandShowProps) {
    const facts = [
        brand.originCountry ? `From ${brand.originCountry}` : null,
        brand.foundedYear ? `Founded ${brand.foundedYear}` : null,
    ].filter((fact): fact is string => fact !== null);

    return (
        <>
            <SeoHead seo={seo} />
            <CatalogBreadcrumbs
                items={[
                    { label: 'Home', href: catalogUrls.home() },
                    { label: 'Brands', href: catalogUrls.brands() },
                    { label: brand.name },
                ]}
            />
            <PageHeader
                title={brand.name}
                eyebrow={facts.length > 0 ? facts.join(' · ') : undefined}
            >
                {brand.description}
            </PageHeader>
            <Section id="brand-products" title={`Products by ${brand.name}`}>
                <ProductGrid
                    products={products}
                    emptyTitle="No products listed for this brand yet"
                />
            </Section>
        </>
    );
}
