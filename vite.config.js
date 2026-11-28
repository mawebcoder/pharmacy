import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { transform } from 'lightningcss';

/**
 * The storefront is light only (owner, 2026-11-28), but TallStackUI's components carry dark: classes, so Tailwind
 * still writes their rules (about 40% of the CSS). This drops every selector that needs the `.dark` class before Vite
 * minifies and hashes the file. lightningcss comes with Vite.
 */
const containsDarkClass = (node) => {
    if (Array.isArray(node)) {
        return node.some(containsDarkClass);
    }

    if (node === null || typeof node !== 'object') {
        return false;
    }

    return (node.type === 'class' && node.name === 'dark') || Object.values(node).some(containsDarkClass);
};

const dropDarkRules = () => ({
    name: 'drop-dark-rules',
    apply: 'build',
    // After Tailwind (also "pre", listed before this) and before Vite minifies and hashes the file.
    enforce: 'pre',
    transform(code, id) {
        if (!id.split('?')[0].endsWith('.css')) {
            return null;
        }

        const result = transform({
            filename: id,
            code: Buffer.from(code),
            visitor: {
                Rule: {
                    style(rule) {
                        const selectors = rule.value.selectors.filter((selector) => !containsDarkClass(selector));

                        if (selectors.length === rule.value.selectors.length) {
                            return undefined;
                        }

                        if (selectors.length === 0) {
                            return [];
                        }

                        rule.value.selectors = selectors;

                        return rule;
                    },
                },
            },
        });

        return { code: result.code.toString(), map: null };
    },
});

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // Self-hosted at build time (roadmap B4): Persian + Latin glyphs only, preload the two main weights.
                bunny('Vazirmatn', {
                    weights: [400, 500, 700],
                    subsets: ['arabic', 'latin'],
                    preload: [{ weight: 400 }, { weight: 700 }],
                    // Metric-matched fallbacks need the extra "fontaine" package; swap + preload is enough here.
                    optimizedFallbacks: false,
                }),
            ],
        }),
        tailwindcss(),
        dropDarkRules(),
    ],
    server: {
        // Listen on all interfaces so the dev server is reachable from the app container's published port.
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
