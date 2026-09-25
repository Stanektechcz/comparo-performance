import { Link } from '@inertiajs/react';
import { ProductGrid } from '@/components/catalog/product-grid';
import { CardGrid, CategoryCard } from '@/components/catalog/summary-cards';
import { buttonStyles } from '@/components/comparo/button-styles';
import { EmptyState } from '@/components/comparo/empty-state';
import { Section } from '@/components/comparo/section';
import { SeoHead } from '@/components/seo/seo-head';
import { useMarket } from '@/hooks/use-shared-props';
import { catalogUrls } from '@/lib/catalog-urls';
import type { HomePageProps } from '@/types/catalog';

export default function Home({ seo, categories, featured }: HomePageProps) {
    const market = useMarket();

    return (
        <>
            <SeoHead seo={seo} />
            <section
                aria-labelledby="hero-heading"
                className="pt-6 pb-4 sm:pt-12"
            >
                <p className="eyebrow text-acc-text">
                    Sports nutrition price comparison · delivering to{' '}
                    {market.name}
                </p>
                <h1
                    id="hero-heading"
                    className="mt-3 max-w-4xl text-[clamp(34px,5.2vw,66px)] leading-[0.98] font-black tracking-[-0.035em] text-balance text-text"
                >
                    Compare what you actually pay.
                </h1>
                <p className="mt-5 max-w-3xl text-[16.5px] leading-relaxed text-text-3">
                    Compare total landed price — product + shipping to your
                    country − the best working coupon — and see why every offer
                    ranks where it does.
                </p>
                <div className="mt-6 flex flex-wrap gap-3">
                    <Link
                        href={catalogUrls.products()}
                        className={buttonStyles.primary}
                    >
                        Browse products
                    </Link>
                    <Link
                        href={catalogUrls.shops()}
                        className={buttonStyles.secondary}
                    >
                        See all shops
                    </Link>
                </div>
            </section>

            <Section
                id="categories"
                title="Categories"
                actions={
                    <Link
                        href={catalogUrls.categories()}
                        className={buttonStyles.tertiary}
                    >
                        All categories <span aria-hidden="true">→</span>
                    </Link>
                }
            >
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
            </Section>

            <Section
                id="featured"
                title="Featured products"
                description={`Lowest totals include shipping to ${market.name}.`}
                actions={
                    <Link
                        href={catalogUrls.products()}
                        className={buttonStyles.tertiary}
                    >
                        All products <span aria-hidden="true">→</span>
                    </Link>
                }
            >
                <ProductGrid
                    products={featured}
                    emptyTitle="No featured products yet"
                />
            </Section>
        </>
    );
}
