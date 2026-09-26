import { clean, selectMarket } from './clean.mjs';

/**
 * Runs the prototype offer pipeline (ranking context capture + landed
 * pricing + market stats) once per market, for every offer. Shared by
 * ranking.json (the `ranking` rows) and pricing.json (the `pricing` and
 * `marketStats` rows).
 */
export function exportOfferPipeline({ seed, app, ix }) {
    const ranking = [];
    const pricing = [];
    const marketStats = [];
    const originalRank = ix.rank;
    let captured = null;

    ix.rank = (ctx) => {
        const result = originalRank(ctx);
        captured = { ctx: clean(ctx), result: clean(result) };

        return result;
    };

    try {
        for (const country of seed.countries) {
            selectMarket(app, country.iso);

            for (const offer of app.allOffers()) {
                captured = null;
                const row = app.offerRow(offer);

                pricing.push({
                    offerId: offer.id,
                    productId: offer.productId,
                    merchantId: offer.merchantId,
                    market: country.iso,
                    ships: !!row.ships,
                    price: offer.price,
                    effective: row.effNum,
                    shipping: row.shipNum,
                    total: row.totalNum,
                    couponCode: row.hasCoupon ? row.couponCode : null,
                    deliveryDaysMax: row.deliveryNum,
                    discountPct: row.discNum,
                    anomaly: !!row.anomaly,
                });

                if (row.ships && captured) {
                    ranking.push({
                        offerId: offer.id,
                        productId: offer.productId,
                        merchantId: offer.merchantId,
                        market: country.iso,
                        ctx: captured.ctx,
                        result: captured.result,
                    });
                }
            }

            for (const product of seed.products) {
                const stats = app.marketStats(product.id);
                marketStats.push({
                    productId: product.id,
                    market: country.iso,
                    ...clean(stats),
                });
            }
        }
    } finally {
        ix.rank = originalRank;
    }

    return { ranking, pricing, marketStats };
}
