#!/usr/bin/env node
/**
 * Staging smoke test — black-box HTTP checks against a running deployment.
 *
 *   node tools/staging/smoke.mjs http://127.0.0.1:8080
 *   node tools/staging/smoke.mjs https://staging.example --json
 *
 * Optional login check (credentials come from the environment only and are
 * never printed): SMOKE_EMAIL, SMOKE_PASSWORD, SMOKE_AFTER_LOGIN (a path that
 * must answer 200 once signed in, e.g. /merchant/feeds).
 *
 * Every check is FAIL (exit code 1) or WARN (reported, exit code unaffected).
 * WARN marks production-hardening gaps that do not break a staging review.
 */
import process from 'node:process';

const SLOW_MS = 1500;
const DEBUG_LEAK =
    /Stack trace|Whoops|vendor[\\/]laravel|Illuminate\\\\|APP_KEY|DB_PASSWORD/;

const args = process.argv.slice(2);
const base = (args.find((arg) => !arg.startsWith('--')) ?? '').replace(
    /\/+$/,
    '',
);
const asJson = args.includes('--json');

if (!/^https?:\/\//.test(base)) {
    console.error('Usage: node tools/staging/smoke.mjs <base-url> [--json]');
    process.exit(2);
}

const results = [];
const jar = new Map();

function record(level, name, detail = '', ms = null) {
    results.push({ level, name, detail, ms });
}

function storeCookies(response) {
    for (const line of response.headers.getSetCookie?.() ?? []) {
        const [pair] = line.split(';');
        const index = pair.indexOf('=');
        jar.set(pair.slice(0, index).trim(), pair.slice(index + 1).trim());
    }
}

function cookieHeader() {
    return [...jar].map(([key, value]) => `${key}=${value}`).join('; ');
}

async function request(path, options = {}) {
    const started = performance.now();
    const response = await fetch(base + path, {
        redirect: 'manual',
        ...options,
        headers: {
            Accept: 'text/html',
            Cookie: cookieHeader(),
            ...options.headers,
        },
    });
    storeCookies(response);
    const body = await response.text();

    return { response, body, ms: Math.round(performance.now() - started) };
}

/**
 * GET a page that must answer 200, optionally server-rendered.
 *
 * @returns {Promise<string|null>} the body on success
 */
async function page(name, path, { ssr = false, json = false } = {}) {
    try {
        const { response, body, ms } = await request(
            path,
            json ? { headers: { Accept: 'application/json' } } : {},
        );

        if (response.status !== 200) {
            record('FAIL', name, `${path} → HTTP ${response.status}`, ms);

            return null;
        }

        if (DEBUG_LEAK.test(body)) {
            record('FAIL', name, `${path} leaks debug output`, ms);

            return null;
        }

        if (ssr && !body.includes('data-server-rendered="true"')) {
            record(
                'FAIL',
                name,
                `${path} is not server-rendered (SSR down?)`,
                ms,
            );

            return body;
        }

        record(
            ms > SLOW_MS ? 'WARN' : 'PASS',
            name,
            ms > SLOW_MS ? `${path} slow` : path,
            ms,
        );

        return body;
    } catch (error) {
        record('FAIL', name, `${path} → ${error.message}`);

        return null;
    }
}

async function guarded(name, path) {
    try {
        const { response, ms } = await request(path);
        const location = response.headers.get('location') ?? '';
        const ok =
            [401, 403, 404].includes(response.status) ||
            (response.status === 302 && location.includes('/login'));
        record(
            ok ? 'PASS' : 'FAIL',
            name,
            `${path} → ${response.status} ${location}`.trim(),
            ms,
        );
    } catch (error) {
        record('FAIL', name, `${path} → ${error.message}`);
    }
}

async function notFound() {
    try {
        const { response, body, ms } = await request('/__smoke-missing-page');
        if (response.status !== 404) {
            record('FAIL', 'not-found page', `HTTP ${response.status}`, ms);
        } else if (DEBUG_LEAK.test(body)) {
            record(
                'FAIL',
                'not-found page',
                'debug output leaked (APP_DEBUG on?)',
                ms,
            );
        } else {
            record('PASS', 'not-found page', '404 without debug output', ms);
        }
    } catch (error) {
        record('FAIL', 'not-found page', error.message);
    }
}

async function securityHeaders() {
    const { response } = await request('/');
    const headers = response.headers;
    const https = base.startsWith('https://');
    const csp = headers.get('content-security-policy') ?? '';
    const expectations = [
        [
            'X-Content-Type-Options',
            headers.get('x-content-type-options') === 'nosniff',
        ],
        [
            'Clickjacking protection',
            Boolean(headers.get('x-frame-options')) ||
                csp.includes('frame-ancestors'),
        ],
        ['Referrer-Policy', Boolean(headers.get('referrer-policy'))],
        ['Content-Security-Policy', csp !== ''],
        [
            'Strict-Transport-Security',
            !https || Boolean(headers.get('strict-transport-security')),
        ],
    ];

    for (const [header, present] of expectations) {
        record(
            present ? 'PASS' : 'WARN',
            `header ${header}`,
            present ? 'present' : 'missing',
        );
    }

    const session =
        (headers.getSetCookie?.() ?? []).find((line) =>
            /session=/i.test(line),
        ) ?? '';
    record(
        /httponly/i.test(session) ? 'PASS' : 'FAIL',
        'session cookie HttpOnly',
        session ? '' : 'no session cookie',
    );
    if (https) {
        record(
            /secure/i.test(session) ? 'PASS' : 'FAIL',
            'session cookie Secure',
        );
    }
}

async function asset(home) {
    const match = home?.match(/\/build\/assets\/[\w.-]+\.js/);
    if (!match) {
        record(
            'FAIL',
            'built assets',
            'no /build/assets/*.js referenced (npm run build missing?)',
        );

        return;
    }

    const { response, ms } = await request(match[0], {
        headers: { Accept: '*/*' },
    });
    record(
        response.status === 200 ? 'PASS' : 'FAIL',
        'built assets',
        `${match[0]} → ${response.status}`,
        ms,
    );
}

async function login() {
    const email = process.env.SMOKE_EMAIL;
    const password = process.env.SMOKE_PASSWORD;
    if (!email || !password) {
        record(
            'WARN',
            'login flow',
            'skipped (SMOKE_EMAIL / SMOKE_PASSWORD not set)',
        );

        return;
    }

    try {
        await request('/login');
        const xsrf = decodeURIComponent(jar.get('XSRF-TOKEN') ?? '');
        const { response, ms } = await request('/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrf,
                Accept: 'text/html',
            },
            body: JSON.stringify({ email, password }),
        });
        const location = response.headers.get('location') ?? '';
        if (response.status !== 302 || location.includes('/login')) {
            record(
                'FAIL',
                'login flow',
                `POST /login → ${response.status} ${location}`.trim(),
                ms,
            );

            return;
        }

        record(
            'PASS',
            'login flow',
            `redirected to ${new URL(location, base).pathname}`,
            ms,
        );
        const after = process.env.SMOKE_AFTER_LOGIN;
        if (after) {
            await page('signed-in page', after, { ssr: true });
        }
    } catch (error) {
        record('FAIL', 'login flow', error.message);
    }
}

async function main() {
    await page('health /up', '/up');
    const home = await page('home', '/', { ssr: true });
    const listing = await page('product listing', '/products', { ssr: true });
    const slug = listing?.match(/\/products\/([a-z0-9][a-z0-9-]*)/)?.[1];

    if (slug) {
        const product = await page('product page', `/products/${slug}`, {
            ssr: true,
        });
        record(
            product?.includes('application/ld+json') ? 'PASS' : 'WARN',
            'product JSON-LD',
            slug,
        );
        await page(
            'public offers API',
            `/api/public/v1/products/${slug}/offers`,
            { json: true },
        );
    } else {
        record(
            'FAIL',
            'product page',
            'no product link on /products (empty catalogue?)',
        );
    }

    await page('search', '/search?q=protein', { ssr: true });
    await page('search suggest API', '/api/public/v1/search/suggest?q=pro', {
        json: true,
    });
    await page('brands', '/brands', { ssr: true });
    await page('categories', '/categories', { ssr: true });
    await page('shops', '/shops', { ssr: true });
    await page('login page', '/login', { ssr: true });
    await guarded('guest → merchant portal', '/merchant/feeds');
    await guarded('guest → staff console', '/admin/catalogue/matching');
    await guarded('guest → account settings', '/settings/profile');
    await guarded('guest → Horizon', '/horizon');
    await notFound();
    await asset(home);
    await securityHeaders();
    jar.clear();
    await login();

    const failed = results.filter((result) => result.level === 'FAIL').length;
    const warned = results.filter((result) => result.level === 'WARN').length;

    if (asJson) {
        console.log(JSON.stringify({ base, failed, warned, results }, null, 2));
    } else {
        for (const { level, name, detail, ms } of results) {
            console.log(
                `${level.padEnd(4)}  ${name.padEnd(28)} ${ms === null ? '' : `${ms} ms`.padStart(8)}  ${detail}`,
            );
        }
        console.log(
            `\n${results.length} checks — ${failed} failed, ${warned} warnings — ${base}`,
        );
    }

    process.exit(failed > 0 ? 1 : 0);
}

await main();
