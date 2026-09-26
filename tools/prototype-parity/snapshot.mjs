import { clean } from './clean.mjs';
import { exportDerived } from './products.mjs';

/** The `database/data/prototype/seed-snapshot.json` content: frozen seed graph + derived aggregates. */
export function buildSnapshot(meta, loaded) {
    const { seed } = loaded;

    return `${JSON.stringify({
        meta,
        seed: clean({ ...seed, helpers: undefined }),
        derived: exportDerived(loaded),
    })}\n`;
}
