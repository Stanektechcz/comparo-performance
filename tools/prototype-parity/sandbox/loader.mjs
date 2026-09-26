/**
 * Loads the original browser prototype (seed graph + scoring engines + the
 * offer pipeline embedded in `Comparo Performance.dc.html`) headless inside a
 * `node:vm` sandbox with a frozen clock.
 *
 * Recipe and parity traps: docs/architecture/scoring-engines-map.md §13.
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

export const ROOT = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '../../..',
);
export const FIXTURE_DIR = path.join(ROOT, 'tests/Fixtures/PrototypeParity');
export const SNAPSHOT_FILE = path.join(
    ROOT,
    'database/data/prototype/seed-snapshot.json',
);
export const HTML_FILE = 'Comparo Performance.dc.html';

/** HTML script order, minus support.js (DOM runtime) and live.js (non-deterministic). */
export const LOAD_ORDER = [
    'seed.js',
    'seed-community.js',
    'seed-geo.js',
    'seed-live.js',
    'seed-gamify.js',
    'seed-dose.js',
    'seed-orders.js',
    'seed-labels.js',
    'seed-network.js',
    'labels.js',
    'seed-visibility.js',
    'seed-governance.js',
    'visibility.js',
    'governance.js',
    'seed-seo.js',
    'seed-intel.js',
    'intel.js',
    'seed-growth.js',
    'growth.js',
    'seed-commercial.js',
    'commercial.js',
    'seed-addons.js',
    'gamify.js',
    'addons.js',
];

export function sha256(file) {
    return crypto
        .createHash('sha256')
        .update(fs.readFileSync(path.join(ROOT, file)))
        .digest('hex');
}

function createSandbox(frozenNow) {
    const memory = new Map();
    const localStorage = {
        getItem: (key) => (memory.has(key) ? memory.get(key) : null),
        setItem: (key, value) => memory.set(key, String(value)),
        removeItem: (key) => memory.delete(key),
        clear: () => memory.clear(),
    };

    class FrozenDate extends Date {
        constructor(...args) {
            super(...(args.length ? args : [frozenNow]));
        }

        static now() {
            return frozenNow;
        }
    }

    const sandbox = {
        console,
        Intl,
        localStorage,
        sessionStorage: localStorage,
        Date: FrozenDate,
        setInterval: () => 0,
        clearInterval: () => {},
        setTimeout: () => 0,
        clearTimeout: () => {},
        addEventListener: () => {},
        removeEventListener: () => {},
        location: {
            hash: '',
            href: 'http://localhost/',
            pathname: '/',
            search: '',
        },
        document: {
            readyState: 'complete',
            addEventListener: () => {},
            removeEventListener: () => {},
            body: null,
            head: { appendChild: () => {} },
            documentElement: {
                setAttribute: () => {},
                style: { setProperty: () => {} },
            },
        },
    };
    sandbox.window = sandbox;
    sandbox.globalThis = sandbox;
    sandbox.Math = Object.create(Math);
    sandbox.Math.random = () => {
        throw new Error(
            'Math.random() reached from the scoring path — fixtures would not be deterministic.',
        );
    };

    return vm.createContext(sandbox);
}

export function loadPrototype() {
    // seed.js defines S.NOW itself; the frozen clock must equal it.
    const frozenNow = Date.parse('2026-09-06T09:00:00Z');
    const context = createSandbox(frozenNow);

    for (const file of LOAD_ORDER) {
        vm.runInContext(
            fs.readFileSync(path.join(ROOT, file), 'utf8'),
            context,
            { filename: file },
        );
    }

    const seed = context.SEED;
    if (!seed || seed.NOW !== frozenNow) {
        throw new Error(
            `SEED.NOW (${seed && seed.NOW}) does not match the frozen clock (${frozenNow}).`,
        );
    }

    const lines = fs
        .readFileSync(path.join(ROOT, HTML_FILE), 'utf8')
        .split(/\r?\n/);
    const start = lines.findIndex((line) =>
        line.startsWith('class Component extends DCLogic'),
    );
    const end = start >= 0 ? lines.indexOf('</script>', start) : -1;
    if (start < 0 || end < 0) {
        throw new Error(
            'Could not locate `class Component extends DCLogic … </script>` in the prototype HTML.',
        );
    }

    const stubBase =
        'class DCLogic { constructor(p) { this.props = p || {}; } ' +
        'setState(u) { const x = typeof u === "function" ? u(this.state) : u; this.state = Object.assign({}, this.state, x); } }\n';
    vm.runInContext(
        `${stubBase}${lines.slice(start, end).join('\n')}\nglobalThis.Component = Component;`,
        context,
        {
            filename: 'component.js',
        },
    );

    const app = new context.Component({});

    return { seed, app, ix: app.ix(), frozenNow, context };
}
