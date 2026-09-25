/**
 * Inertia page-prop contracts for the public catalogue.
 *
 * These mirror the PHP presenters in app/Http/Presenters exactly. The server
 * owns every number: prices, totals, ranks, trust and compliance are computed
 * server-side and only formatted here. Money is always integer minor units.
 */

export type Money = { minor: number; currency: string };

export type MarketOption = { code: string; name: string; currency: string };

export type Market = {
    code: string;
    name: string;
    currency: string;
    locale: string;
    options: MarketOption[];
};

export type SeoAlternate = { hreflang: string; href: string };

export type SeoHead = {
    title: string;
    description: string;
    canonical: string;
    robots: string;
    alternates: SeoAlternate[];
    openGraph: Record<string, string>;
    jsonLd: Record<string, unknown>[];
};

export type RankBand = 'exceptional' | 'excellent' | 'good' | 'fair' | 'low';

export type RankPart = {
    key: string;
    label: string;
    points: number;
    maximum: number;
};

export type RankPenalty = { label: string; points: number };

export type RankExplanation = {
    score: number;
    band: RankBand;
    label: string;
    parts: RankPart[];
    penalties: RankPenalty[];
    /** Integrity checks that affected the score but are not itemised publicly. */
    withheldChecks: number;
    eligibleBestBuy: boolean;
    version: string;
    evaluatedAt: string;
};

export type TrustBand = 'ok' | 'accent' | 'warn' | 'danger';

export type TrustSignal = {
    label: string;
    value: string;
    percent: number;
    good: boolean;
};

export type Trust = {
    score: number;
    label: string;
    band: TrustBand;
    signals: TrustSignal[];
};

export type CouponType = 'percent' | 'fixed' | 'free_shipping';

export type AppliedCoupon = {
    code: string;
    title: string | null;
    type: CouponType;
    stateLabel: string;
    exclusive: boolean;
    saving: Money;
};

export type ShippingBasis =
    | 'not_shipping_to_market'
    | 'zone_rate'
    | 'free_over_threshold'
    | 'free_shipping_coupon';

export type LandedPrice = {
    base: Money;
    discount: Money;
    effective: Money;
    shipping: Money;
    shippingBasis: ShippingBasis;
    total: Money;
    freeShippingThreshold: Money | null;
    coupon: AppliedCoupon | null;
    deliveryDays: [number, number] | null;
    carrier: string | null;
    /** Total converted to the market's display currency (indicative, dated rate). */
    displayTotal: Money | null;
};

export type PriceConfidence = {
    score: number;
    level: string;
    signals: { label: string; ok: boolean; points: number }[];
};

export type ReferencePrice = {
    amount: Money;
    /** Null when the reference price is unverified — the discount is withheld. */
    discountPercent: number | null;
    verified: boolean;
};

export type OfferRow = {
    id: number;
    merchant: { name: string; slug: string; verified: boolean; trust: Trust };
    variantLabel: string | null;
    packLabel: string | null;
    availability: { key: string; label: string };
    price: LandedPrice;
    referencePrice: ReferencePrice | null;
    rank: RankExplanation;
    priceConfidence: PriceConfidence;
    updatedAt: string;
    freshnessHours: number;
    /** Null whenever the product is not purchasable in the market (compliance). */
    purchaseUrl: string | null;
    isBestValue: boolean;
};

export type ComplianceStatus =
    | 'allowed'
    | 'restricted'
    | 'prescription_only'
    | 'not_allowed'
    | 'unknown';

export type Compliance = {
    status: ComplianceStatus;
    label: string;
    reason: string | null;
    source: string | null;
    reviewedAt: string | null;
    offersVisible: boolean;
    purchasable: boolean;
    recommendable: boolean;
    underReview: boolean;
};

export type PriceBadge = { key: string; label: string; explanation: string };

export type PriceHistory = {
    currency: string;
    /** `prototype_demo` = fictional demo series; label it as such in the UI. */
    source: 'aggregated' | 'prototype_demo' | null;
    points: { date: string; min: number }[];
    stats: {
        average30: number;
        average90: number;
        low90: number;
        low: number;
        high: number;
        median: number;
        current: number;
        volatilityPercent: number;
    } | null;
    badge: PriceBadge | null;
    timing: PriceBadge | null;
    trend: { key: string; label: string } | null;
};

export type Rating = {
    average: number | null;
    count: number;
    source: string | null;
};

export type ProductSummary = {
    id: number;
    slug: string;
    name: string;
    brand: { name: string; slug: string };
    category: { name: string; slug: string };
    packLabel: string;
    lowestTotal: Money | null;
    offerCount: number;
    rating: Rating;
};

export type Ingredient = {
    name: string;
    slug: string;
    amountMg: number | null;
    isCarrier: boolean;
    nrvPercent: number | null;
};

export type ProductDetail = {
    id: number;
    slug: string;
    name: string;
    brand: { name: string; slug: string };
    category: { name: string; slug: string };
    ean: string | null;
    packLabel: string;
    servings: number | null;
    shortDescription: string | null;
    description: string | null;
    flavours: string[];
    packs: string[];
    ingredients: Ingredient[];
    completeness: { percent: number; missing: string[] };
    rrp: Money | null;
    rating: Rating;
};

export type OfferSummary = {
    /** Offers of this product in the database. */
    total: number;
    /** Offers listed in this market. */
    shown: number;
    /** Offers withheld because their price is flagged as anomalous. */
    withheldFlagged: number;
    /** Offers from shops that do not deliver to this market. */
    notShipping: number;
    /** Offers from shops that deliver here, but whose shipping cost has no known exchange rate into the offer currency. */
    shippingUnavailable: number;
    lowestTotal: Money | null;
    bestValueOfferId: number | null;
};

export type ProductPageProps = {
    seo: SeoHead;
    product: ProductDetail;
    compliance: Compliance;
    offers: OfferRow[];
    offerSummary: OfferSummary;
    priceHistory: PriceHistory;
};

export type Paginated<T> = {
    data: T[];
    meta: {
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    links: { prev: string | null; next: string | null };
};

export type CategorySummary = {
    slug: string;
    name: string;
    description: string | null;
    productCount: number;
};

export type BrandSummary = {
    slug: string;
    name: string;
    originCountry: string | null;
    productCount: number;
};

export type ShopSummary = {
    slug: string;
    name: string;
    verified: boolean;
    trust: Trust;
    shipsToMarket: boolean;
    offerCount: number;
};

export type HomePageProps = {
    seo: SeoHead;
    categories: CategorySummary[];
    featured: ProductSummary[];
};

export type ProductIndexProps = {
    seo: SeoHead;
    products: Paginated<ProductSummary>;
};

export type BrandIndexProps = { seo: SeoHead; brands: BrandSummary[] };

export type BrandShowProps = {
    seo: SeoHead;
    brand: {
        slug: string;
        name: string;
        description: string | null;
        foundedYear: number | null;
        originCountry: string | null;
    };
    products: ProductSummary[];
};

export type CategoryIndexProps = {
    seo: SeoHead;
    categories: CategorySummary[];
};

export type CategoryShowProps = {
    seo: SeoHead;
    category: { slug: string; name: string; description: string | null };
    products: ProductSummary[];
};

export type ShopIndexProps = { seo: SeoHead; shops: ShopSummary[] };

export type ShopShowProps = {
    seo: SeoHead;
    shop: {
        slug: string;
        name: string;
        website: string | null;
        description: string | null;
        verified: boolean;
        returnDays: number | null;
        trust: Trust;
        shipping: {
            cost: Money;
            deliveryDays: [number, number];
            freeOver: Money | null;
        } | null;
        markets: string[];
    };
    products: ProductSummary[];
};
