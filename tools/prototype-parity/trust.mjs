import { clean } from './clean.mjs';

export function exportMerchantScores({ seed, app, ix }) {
    return seed.merchants.map((merchant) => {
        const m = app.M(merchant.id);

        return {
            merchantId: merchant.id,
            trust: clean(ix.trust(m)),
            risk: clean(ix.risk(m)),
            deliveryReliability: clean(ix.deliveryReliability(m)),
            rating: clean(app.ratingOf('merchant', merchant.id)),
        };
    });
}
