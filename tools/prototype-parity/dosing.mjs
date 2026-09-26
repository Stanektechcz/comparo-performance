import { selectMarket } from './clean.mjs';

export function exportDosing({ seed, app }) {
    const cases = [];
    for (const country of seed.countries) {
        selectMarket(app, country.iso);
        for (const product of seed.products) {
            cases.push({
                productId: product.id,
                market: country.iso,
                servingMg: app.servingMg(product),
                activeMg: app.activeMg(product),
                packActiveG: app.packActiveG(product),
                costPerActiveG: app.costPerActiveG(product),
            });
        }
    }

    return cases;
}
