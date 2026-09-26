/** Presentation-only keys that must never be part of a parity contract. */
const PRESENTATION_KEYS = new Set([
    'color',
    'colour',
    'width',
    'rankWidth',
    'rankColor',
]);

/** Deep copy that drops functions and presentation-only keys, and replaces object refs by ids. */
export function clean(value) {
    if (value === undefined) {
        return null;
    }

    return JSON.parse(
        JSON.stringify(value, (key, v) => {
            if (PRESENTATION_KEYS.has(key) || typeof v === 'function') {
                return undefined;
            }
            if (key === 'product' && v && typeof v === 'object' && 'id' in v) {
                return v.id;
            }

            return v;
        }),
    );
}

export function selectMarket(app, iso) {
    app.state.country = iso;
    app.state.currency = 'EUR';
    app._mk = {};
    app._rc = {};
}
