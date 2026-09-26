export function exportCompliance({ seed, app }) {
    const cases = [];
    for (const product of seed.products) {
        for (const country of seed.countries) {
            const decision = app.comp(product.id, country.iso);
            cases.push({
                productId: product.id,
                market: country.iso,
                status: decision.status,
                source: decision.source,
                explicitRule: seed.complianceRules.some(
                    (r) =>
                        r.productId === product.id && r.country === country.iso,
                ),
            });
        }
    }

    return cases;
}
