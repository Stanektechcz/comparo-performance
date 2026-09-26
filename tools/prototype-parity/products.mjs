import { clean } from './clean.mjs';

export function exportProductScores({ seed, app, ix }) {
    return seed.products.map((product) => {
        const stats = ix.histStats(product);

        return {
            productId: product.id,
            completion: clean(ix.completion(product.id)),
            histStats: clean(stats),
            priceBadge: clean(ix.priceBadge(stats.cur, stats)),
            timing: clean(ix.timing(stats.cur, stats)),
            forecast: clean(ix.forecast(product)),
            rating: clean(app.ratingOf('product', product.id)),
        };
    });
}

export function exportOfferScores({ seed, ix }) {
    return seed.offers.map((offer) => ({
        offerId: offer.id,
        priceConfidence: clean(ix.priceConfidence(offer)),
        fakeDiscount: clean(ix.fakeDiscount(offer)),
    }));
}

/**
 * Values the prototype derives from data that Laravel does not own yet
 * (credibility-weighted review averages need the Reviews context, Phase 4).
 * The demo importer stores them as explicitly labelled derived aggregates.
 */
export function exportDerived({ seed, app }) {
    const ratings = (type, rows) =>
        Object.fromEntries(
            rows.map((row) => {
                const rating = app.ratingOf(type, row.id);

                return [row.id, { average: rating.avg, count: rating.count }];
            }),
        );

    return {
        note: 'Derived by the prototype (ratingOf). Recomputed natively once the Reviews context lands.',
        merchantRatings: ratings('merchant', seed.merchants),
        productRatings: ratings('product', seed.products),
    };
}
