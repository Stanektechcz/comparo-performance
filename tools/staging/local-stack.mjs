#!/usr/bin/env node
/**
 * Local staging stack — runs a production-like Comparo release on one machine
 * without Docker (ADR-0011): PostgreSQL (embedded-postgres), Meilisearch, the
 * web server, the Inertia SSR server, queue workers and the scheduler.
 *
 *   node tools/staging/local-stack.mjs start <root>   foreground supervisor (Ctrl+C stops all)
 *   node tools/staging/local-stack.mjs stop <root>    stops a supervisor started elsewhere
 *
 * <root> layout (outside the repository; see docs/operations/staging.md):
 *   app/            the release (git checkout, composer --no-dev, built assets, .env with APP_ENV=staging)
 *   runtime/pg/     an npm directory with `embedded-postgres` installed
 *   runtime/meili/  the meilisearch binary
 *   data/           persistent PostgreSQL and Meilisearch state (created on first start)
 *   logs/           one log file per process
 *
 * Ports, credentials and the Meilisearch key are read from app/.env and never printed.
 * PHP is taken from PHP_BINARY, else `php` on PATH. PHP processes are restarted
 * when they exit (like supervisord), after RESTART_DELAY_MS.
 */
import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import { createRequire } from 'node:module';
import net from 'node:net';
import path from 'node:path';
import process from 'node:process';
import { pathToFileURL } from 'node:url';

const RESTART_DELAY_MS = 3000;
const [command, rootArg] = process.argv.slice(2);

if (!['start', 'stop'].includes(command) || !rootArg) {
    console.error(
        'Usage: node tools/staging/local-stack.mjs <start|stop> <root>',
    );
    process.exit(2);
}

const root = path.resolve(rootArg);
const app = path.join(root, 'app');
const logs = path.join(root, 'logs');
const pidFile = path.join(root, 'stack.pid');
const pgData = path.join(root, 'data', 'pg');
const php = process.env.PHP_BINARY ?? 'php';
const isWindows = process.platform === 'win32';

function readEnv(file) {
    const env = {};
    for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
        const match = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
        if (match) {
            env[match[1]] = match[2].replace(/^(['"])(.*)\1$/, '$2');
        }
    }

    return env;
}

function pgBinary(name) {
    const scope = path.join(
        root,
        'runtime',
        'pg',
        'node_modules',
        '@embedded-postgres',
    );
    const platformDir = fs.readdirSync(scope)[0];

    return path.join(scope, platformDir, 'native', 'bin', name);
}

function waitForPort(port, label, timeoutMs = 60_000) {
    const deadline = Date.now() + timeoutMs;

    return new Promise((resolve, reject) => {
        const attempt = () => {
            const socket = net.connect({ host: '127.0.0.1', port }, () => {
                socket.end();
                resolve();
            });
            socket.on('error', () => {
                socket.destroy();
                if (Date.now() > deadline) {
                    reject(new Error(`${label} did not open port ${port}`));
                } else {
                    setTimeout(attempt, 500);
                }
            });
        };
        attempt();
    });
}

async function startPostgres(env) {
    const pgDir = path.join(root, 'runtime', 'pg');
    const entry = pathToFileURL(
        path.join(
            pgDir,
            'node_modules',
            'embedded-postgres',
            'dist',
            'index.js',
        ),
    ).href;
    const { default: EmbeddedPostgres } = await import(entry);
    const { Client } = createRequire(path.join(pgDir, 'package.json'))('pg');
    const credentials = {
        user: env.DB_USERNAME,
        password: env.DB_PASSWORD,
        port: Number(env.DB_PORT),
    };
    const pg = new EmbeddedPostgres({
        databaseDir: pgData,
        persistent: true,
        ...credentials,
    });

    if (!fs.existsSync(path.join(pgData, 'PG_VERSION'))) {
        await pg.initialise();
    }
    await pg.start();

    // initdb may pick a legacy Windows code page: application databases are always UTF8 from template0.
    const client = new Client({
        host: '127.0.0.1',
        database: 'postgres',
        ...credentials,
    });
    await client.connect();
    const exists = await client.query(
        'SELECT 1 FROM pg_database WHERE datname = $1',
        [env.DB_DATABASE],
    );
    if (exists.rowCount === 0) {
        const name = env.DB_DATABASE.replace(/"/g, '');
        await client.query(
            `CREATE DATABASE "${name}" ENCODING 'UTF8' LC_COLLATE 'C' LC_CTYPE 'C' TEMPLATE template0`,
        );
    }
    await client.end();

    return pg;
}

function supervise(name, binary, args, cwd = app) {
    const log = fs.openSync(path.join(logs, `${name}.log`), 'a');
    const state = { child: null, stopping: false };

    const launch = () => {
        state.child = spawn(binary, args, {
            cwd,
            stdio: ['ignore', log, log],
            windowsHide: true,
        });
        state.child.on('exit', (code) => {
            fs.writeSync(log, `\n[local-stack] ${name} exited with ${code}\n`);
            if (!state.stopping) {
                setTimeout(launch, RESTART_DELAY_MS);
            }
        });
    };
    launch();

    return state;
}

function killTree(pid) {
    if (isWindows) {
        spawnSync('taskkill', ['/PID', String(pid), '/T', '/F'], {
            stdio: 'ignore',
        });

        return;
    }

    try {
        process.kill(pid, 'SIGTERM');
    } catch {
        // already gone
    }
}

async function start() {
    const env = readEnv(path.join(app, '.env'));
    fs.mkdirSync(logs, { recursive: true });
    fs.mkdirSync(path.join(root, 'data', 'meili'), { recursive: true });
    fs.writeFileSync(pidFile, String(process.pid));

    const pg = await startPostgres(env);
    const meiliPort = new URL(env.MEILISEARCH_HOST).port;
    const meiliBinary = fs
        .readdirSync(path.join(root, 'runtime', 'meili'))
        .find((file) => file.startsWith('meilisearch'));
    const processes = [
        supervise(
            'meilisearch',
            path.join(root, 'runtime', 'meili', meiliBinary),
            [
                '--db-path',
                path.join(root, 'data', 'meili'),
                '--http-addr',
                `127.0.0.1:${meiliPort}`,
                '--master-key',
                env.MEILISEARCH_KEY,
                '--env',
                'production',
                '--no-analytics',
            ],
            root,
        ),
    ];
    await waitForPort(Number(meiliPort), 'Meilisearch');

    const appPort = new URL(env.APP_URL).port || '80';
    processes.push(
        supervise('web', php, [
            'artisan',
            'serve',
            '--host=127.0.0.1',
            `--port=${appPort}`,
            '--no-reload',
        ]),
        supervise('ssr', php, ['artisan', 'inertia:start-ssr']),
        supervise('queue', php, [
            'artisan',
            'queue:work',
            'database',
            '--queue=search,analytics,default',
            '--sleep=1',
            '--tries=3',
        ]),
        supervise('queue-long', php, [
            'artisan',
            'queue:work',
            'database-long',
            '--queue=feed-import,matching,pricing',
            '--sleep=1',
            '--timeout=900',
        ]),
        supervise('scheduler', php, ['artisan', 'schedule:work']),
    );
    await waitForPort(Number(appPort), 'web server');
    console.log(
        `[local-stack] staging is up at ${env.APP_URL} (logs: ${logs})`,
    );

    const shutdown = async () => {
        for (const state of processes) {
            state.stopping = true;
            if (state.child?.pid) {
                killTree(state.child.pid);
            }
        }
        await pg.stop().catch(() => {});
        fs.rmSync(pidFile, { force: true });
        process.exit(0);
    };
    process.on('SIGINT', shutdown);
    process.on('SIGTERM', shutdown);
}

function stop() {
    // Stop PostgreSQL cleanly first; killing the supervisor tree would otherwise force a crash recovery.
    if (fs.existsSync(path.join(pgData, 'postmaster.pid'))) {
        spawnSync(
            pgBinary(isWindows ? 'pg_ctl.exe' : 'pg_ctl'),
            ['stop', '-D', pgData, '-m', 'fast'],
            { stdio: 'inherit' },
        );
    }
    if (fs.existsSync(pidFile)) {
        killTree(Number(fs.readFileSync(pidFile, 'utf8')));
        fs.rmSync(pidFile, { force: true });
    }
    console.log('[local-stack] stopped');
}

if (command === 'start') {
    await start();
} else {
    stop();
}
