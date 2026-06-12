import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import os from 'node:os';
import { defineConfig } from 'vite';

function getLocalIp(): string {
    for (const iface of Object.values(os.networkInterfaces())) {
        for (const net of iface ?? []) {
            if (net.family === 'IPv4' && !net.internal) {
                return net.address;
            }
        }
    }
    return 'localhost';
}

const host = getLocalIp();

export default defineConfig({
    server: {
        host: '0.0.0.0',
        cors: true,
        hmr: { host },
    },
    build: {
        rollupOptions: {
            // Silence noisy /* #__PURE__ */ annotation warnings coming from
            // third-party deps (e.g. reka-ui's bundled @vueuse/core).
            onLog(level, log, handler) {
                if (log.code === 'INVALID_ANNOTATION' && log.id?.includes('node_modules')) {
                    return;
                }
                handler(level, log);
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
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
    ],
});
