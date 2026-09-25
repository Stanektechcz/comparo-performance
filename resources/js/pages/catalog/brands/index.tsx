import { PageHeader } from '@/components/catalog/page-header';
import { BrandCard, CardGrid } from '@/components/catalog/summary-cards';
import { EmptyState } from '@/components/comparo/empty-state';
import { CatalogBreadcrumbs } from '@/components/navigation/catalog-breadcrumbs';
import { SeoHead } from '@/components/seo/seo-head';
import { catalogUrls } from '@/lib/catalog-urls';
import type { BrandIndexProps } from '@/types/catalog';

export default function BrandIndex({ seo, brands }: BrandIndexProps) {
    return (
        <>
            <SeoHead seo={seo} />
            <CatalogBreadcrumbs
                items={[
                    { label: 'Home', href: catalogUrls.home() },
                    { label: 'Brands' },
                ]}
            />
            <PageHeader title="Brands">
                Every brand in the catalogue, with the number of products we
                compare.
            </PageHeader>
            <section aria-labelledby="brand-list-heading" className="pt-6">
                <h2 id="brand-list-heading" className="sr-only">
                    Brand list
                </h2>
                {brands.length === 0 ? (
                    <EmptyState title="No brands published yet" />
                ) : (
                    <CardGrid>
                        {brands.map((brand) => (
                            <li key={brand.slug}>
                                <BrandCard brand={brand} />
                            </li>
                        ))}
                    </CardGrid>
                )}
            </section>
        </>
    );
}
