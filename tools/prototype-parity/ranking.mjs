import { clean } from './clean.mjs';

/** Synthetic `ix.rank()` cases covering every ranking factor and edge case. */
export function syntheticRankingCases(ix) {
    const base = {
        total: 30,
        marketMin: 28,
        marketMedian: 31,
        ship: 3.9,
        shipMedian: 4.5,
        deliveryDays: 4,
        rating: 4.4,
        reviewCount: 320,
        trust: 82,
        freshnessHours: 6,
        availability: 'in_stock',
        hasValidCoupon: false,
        completeness: 0.875,
        anomaly: false,
        fakeDiscount: false,
        linkFlag: false,
        complianceUnknown: false,
        complianceBlocked: false,
        riskLevel: 'LOW',
    };
    const variants = {
        baseline: {},
        'falsy fallbacks (JS ||)': {
            trust: 0,
            rating: 0,
            reviewCount: 0,
            deliveryDays: 0,
            freshnessHours: 0,
            completeness: 0,
            shipMedian: 0,
        },
        'cheapest in market': { total: 28 },
        'more than 35 % above market min': { total: 40 },
        'stale feed': { freshnessHours: 60 },
        'price anomaly': { anomaly: true },
        'fake reference price': { fakeDiscount: true },
        'broken outbound link (hidden)': { linkFlag: true },
        'compliance unknown': { complianceUnknown: true },
        'compliance blocked': { complianceBlocked: true },
        'risk HIGH (hidden)': { riskLevel: 'HIGH' },
        'risk CRITICAL (hidden)': { riskLevel: 'CRITICAL' },
        'low stock': { availability: 'low_stock' },
        preorder: { availability: 'preorder' },
        'out of stock': { availability: 'out_of_stock' },
        'valid coupon': { hasValidCoupon: true },
        'free shipping': { ship: 0 },
        'slow delivery': { deliveryDays: 12 },
        'everything wrong': {
            total: 60,
            anomaly: true,
            fakeDiscount: true,
            linkFlag: true,
            freshnessHours: 100,
            riskLevel: 'CRITICAL',
            complianceUnknown: true,
            availability: 'out_of_stock',
            trust: 10,
            rating: 2,
        },
        'custom weights (Ranking Lab)': {
            weights: {
                price: 40,
                trust: 10,
                delivery: 10,
                reviews: 10,
                freshness: 10,
                availability: 10,
                shipping: 10,
            },
        },
        'zero weight factor': {
            weights: {
                price: 30,
                trust: 0,
                delivery: 14,
                reviews: 12,
                freshness: 10,
                availability: 8,
                shipping: 6,
            },
        },
    };

    return Object.entries(variants).map(([name, override]) => {
        const ctx = { ...base, ...override };

        return { name, ctx: clean(ctx), result: clean(ix.rank(ctx)) };
    });
}
