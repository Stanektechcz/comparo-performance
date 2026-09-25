import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

/**
 * The original browser prototype lives at the repository root and is the
 * behaviour specification for the migration. It must never be linted,
 * formatted or rewritten by tooling (see docs/adr/0001).
 */
const prototypeSpecification = [
    '*.dc.html',
    '[A-Z]*.md',
    'seed*.js',
    'addons.js',
    'commercial.js',
    'gamify.js',
    'governance.js',
    'growth.js',
    'intel.js',
    'labels.js',
    'live.js',
    'support.js',
    'visibility.js',
    'llms.txt',
    'robots.txt',
    'sitemap*.xml',
    'screenshots/**',
    'uploads/**',
    'docs/**',
    'tests/Fixtures/**',
    // Generated, byte-stable parity artefacts (tools/prototype-parity --check).
    'database/data/**',
    '.agents/**',
    '.claude/**',
    '.codex/**',
    '.mcp.json',
    'boost.json',
];

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Archivo', {
                    weights: [400, 500, 600, 700, 800, 900],
                    subsets: ['latin', 'latin-ext'],
                    optimizedFallbacks: false,
                    preload: [
                        { weight: 400 },
                        { weight: 700 },
                        { weight: 900 },
                    ],
                }),
                bunny('JetBrains Mono', {
                    weights: [400, 500, 700],
                    subsets: ['latin', 'latin-ext'],
                    preload: [{ weight: 700 }],
                    optimizedFallbacks: false,
                }),
            ],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
            ...prototypeSpecification,
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
            ...prototypeSpecification,
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
