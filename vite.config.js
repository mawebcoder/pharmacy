import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

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
