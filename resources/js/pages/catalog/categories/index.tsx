import { PageHeader } from '@/components/catalog/page-header';
import { CardGrid, CategoryCard } from '@/components/catalog/summary-cards';
import { EmptyState } from '@/components/comparo/empty-state';
import { CatalogBreadcrumbs } from '@/components/navigation/catalog-breadcrumbs';
import { SeoHead } from '@/components/seo/seo-head';
import { catalogUrls } from '@/lib/catalog-urls';
import type { CategoryIndexProps } from '@/types/catalog';

export default function CategoryIndex({ seo, categories }: CategoryIndexProps) {
    return (
        <>
            <SeoHead seo={seo} />
            <CatalogBreadcrumbs
                items={[
                    { label: 'Home', href: catalogUrls.home() },
                    { label: 'Categories' },
                ]}
            />
            <PageHeader title="Categories">
                Browse the catalogue by product type.
            </PageHeader>
            <section aria-labelledby="category-list-heading" className="pt-6">
                <h2 id="category-list-heading" className="sr-only">
                    Category list
                </h2>
                {categories.length === 0 ? (
                    <EmptyState title="No categories published yet" />
                ) : (
                    <CardGrid>
                        {categories.map((category) => (
                            <li key={category.slug}>
                                <CategoryCard category={category} />
                            </li>
                        ))}
                    </CardGrid>
                )}
            </section>
        </>
    );
}
