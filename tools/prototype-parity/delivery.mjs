import { clean } from './clean.mjs';

export function exportDelivery({ seed, app }) {
    const cases = [];
    for (const merchant of seed.merchants) {
        for (const iso of Object.keys(merchant.zones || {}).sort()) {
            cases.push({
                merchantId: merchant.id,
                market: iso,
                stats: clean(app.deliveryStats(merchant.id, iso)),
            });
        }
    }

    return cases;
}
