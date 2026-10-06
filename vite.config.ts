import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { copyFileSync, existsSync, mkdirSync } from 'node:fs';
import { defineConfig, lazyPlugins } from 'vite-plus';

/**
 * Telefonla ön kayıtta pasaportu okuyan açık kaynak yazı tanıma (tesseract.js) dosyaları kendi
 * sunucumuzdan verilir (yolcunun telefonu başka bir siteye bağlanmasın): public/vendor/tesseract.
 */
function tesseractAssets() {
    const copy = () => {
        const dir = 'public/vendor/tesseract';
        mkdirSync(`${dir}/lang`, { recursive: true });
        const files: [string, string][] = [
            [
                'node_modules/tesseract.js/dist/worker.min.js',
                `${dir}/worker.min.js`,
            ],
            [
                'node_modules/@tesseract.js-data/eng/4.0.0_best_int/eng.traineddata.gz',
                `${dir}/lang/eng.traineddata.gz`,
            ],
            ...['lstm', 'simd-lstm', 'relaxedsimd-lstm'].map(
                (v): [string, string] => [
                    `node_modules/tesseract.js-core/tesseract-core-${v}.wasm.js`,
                    `${dir}/tesseract-core-${v}.wasm.js`,
                ],
            ),
        ];

        for (const [from, to] of files) {
            if (existsSync(from)) {
                copyFileSync(from, to);
            }
        }
    };

    return { name: 'marhal-tesseract-assets', buildStart: copy };
}

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                // Tema yazı tipleri (resources/css/app.css → --font-body / --font-display)
                bunny('Inter', { weights: [400, 500, 600] }),
                bunny('Fraunces', { weights: [500, 600] }),
                bunny('Plus Jakarta Sans', { weights: [400, 500, 600] }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
        tesseractAssets(),
    ]),
    server: {
        ...(process.env.VITE_IN_DOCKER
            ? { host: '0.0.0.0', hmr: { host: 'localhost' } }
            : {}),
        watch: {
            usePolling: !!process.env.VITE_IN_DOCKER,
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
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
