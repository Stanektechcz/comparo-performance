/** A fresh engine (empty memo) over the seed, optionally with its own catalogue. */
export function freshEngine(context, seed, productIds = null) {
    if (productIds === null) {
        return context.ComparoIntel(seed);
    }
    const products = productIds.map((id) => {
        const product = seed.products.find((p) => p.id === id);
        if (!product) {
            throw new Error(`Unknown product ${id} in a matching case.`);
        }

        return product;
    });

    return context.ComparoIntel({ ...seed, products });
}
