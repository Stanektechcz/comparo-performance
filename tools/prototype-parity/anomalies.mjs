import { freshEngine } from './engine.mjs';

/** Prototype amounts are EUR floats with at most two decimals. */
const toMinor = (amount) => Math.round(amount * 100);

const ANOMALY_KINDS = {
    'Zero price': 'too_low',
    'Far below market': 'too_low',
    'Far above market': 'too_high',
};

export function anomalyMeta(meta) {
    return {
        ...meta,
        note: "intel.js anomalies(): upper median of the product's positive prices (the offer included); zero, < 45 % or > 220 % of it is flagged.",
    };
}

/** `ix.anomalies()` offer flags (import-validation rows carry no offer and are skipped). */
function anomalyFlags(engine) {
    return engine
        .anomalies()
        .filter((a) => a.offerId !== null)
        .map((a) => {
            if (!(a.kind in ANOMALY_KINDS)) {
                throw new Error(`Unknown anomaly kind "${a.kind}".`);
            }

            return {
                offerId: a.offerId,
                productId: a.productId,
                kind: ANOMALY_KINDS[a.kind],
                medianMinor: toMinor(a.median),
            };
        })
        .sort((a, b) => a.offerId - b.offerId);
}

/** Price sets around the zero / 45 % / 220 % thresholds and the upper median. */
const ANOMALY_SYNTHETIC = [
    ['zero price among priced offers', [0, 30, 40]],
    ['far below the median', [10, 30, 40]],
    ['far above the median', [30, 40, 100]],
    ['just below 45 % of the median', [44.99, 100]],
    ['exactly 45 % of the median', [45, 100]],
    ['upper median includes the offer itself', [100, 220]],
    ['exactly 220 % of the median', [100, 100, 220]],
    ['just above 220 % of the median', [100, 100, 220.01]],
    ['even count takes the upper middle', [10, 20, 30, 40]],
    ['a lone priced offer', [50]],
    ['a lone zero price has no median', [0]],
    ['zero prices against one priced offer', [0, 0, 30]],
    [
        'odd count, cheap outlier on a tight market',
        [12.99, 29.9, 31.5, 33, 34.95],
    ],
];

export function exportAnomalies({ seed, context }) {
    const offers = seed.offers
        .map((o) => ({
            offerId: o.id,
            productId: o.productId,
            priceMinor: toMinor(o.price),
        }))
        .sort((a, b) => a.offerId - b.offerId);

    const template = seed.offers[0];
    const product = seed.products.find((p) => p.id === template.productId);
    const synthetic = ANOMALY_SYNTHETIC.map(([name, prices]) => {
        const caseOffers = prices.map((price, index) => ({
            ...template,
            id: index + 1,
            productId: product.id,
            price,
            ix: undefined,
        }));
        const engine = context.ComparoIntel({
            ...seed,
            products: [product],
            offers: caseOffers,
            ix: { ...seed.ix, feedDiff: [] },
        });

        return {
            name,
            prices: caseOffers.map((o) => toMinor(o.price)),
            flags: anomalyFlags(engine).map((flag) => ({
                index: flag.offerId - 1,
                kind: flag.kind,
                medianMinor: flag.medianMinor,
            })),
        };
    });

    return {
        offers,
        flags: anomalyFlags(freshEngine(context, seed)),
        synthetic,
    };
}
