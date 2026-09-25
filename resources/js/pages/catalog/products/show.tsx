import { ProductAbout } from '@/components/catalog/product-about';
import { ProductHero } from '@/components/catalog/product-hero';
import { ComplianceBanner } from '@/components/compliance/compliance-banner';
import { CatalogBreadcrumbs } from '@/components/navigation/catalog-breadcrumbs';
import { WhereToBuy } from '@/components/offers/where-to-buy';
import { PriceHistoryChart } from '@/components/pricing/price-history-chart';
import { PriceIntelligence } from '@/components/pricing/price-intelligence';
import { SeoHead } from '@/components/seo/seo-head';
import { catalogUrls } from '@/lib/catalog-urls';
import type { Compliance, ProductPageProps } from '@/types/catalog';

/** Products that may not be sold in the market get an informational page. */
function isInformationalOnly(compliance: Compliance): boolean {
    return (
        !compliance.offersVisible ||
        compliance.status === 'prescription_only' ||
        compliance.status === 'not_allowed'
    );
}

export default function ProductShow({
    seo,
    product,
    compliance,
    offers,
    offerSummary,
    priceHistory,
}: ProductPageProps) {
    const informationalOnly = isInformationalOnly(compliance);

    return (
        <>
            <SeoHead seo={seo} />
            <CatalogBreadcrumbs
                items={[
                    { label: 'Home', href: catalogUrls.home() },
                    {
                        label: product.category.name,
                        href: catalogUrls.category(product.category.slug),
                    },
                    { label: product.name },
                ]}
            />
            <ComplianceBanner
                compliance={compliance}
                productName={product.name}
            />
            <ProductHero
                product={product}
                summary={offerSummary}
                showPrice={!informationalOnly}
            />
            {informationalOnly ? null : (
                <>
                    <WhereToBuy
                        productName={product.name}
                        offers={offers}
                        summary={offerSummary}
                        compliance={compliance}
                    />
                    <PriceIntelligence history={priceHistory} />
                    <PriceHistoryChart history={priceHistory} />
                </>
            )}
            <ProductAbout product={product} />
        </>
    );
}
