import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

/*
 * Built with the application's own Vite and Vue, as the other fields are,
 * so the field needs no node_modules of its own. Vue and Nova's mixins are
 * the copies Nova has already loaded into the page, as window.Vue and
 * window.LaravelNova, so they are left out of the bundle.
 *
 * "@" points at the application's resources/js, as it does for the
 * Dashboard, so the calendar's behaviour (useSpecificDays) is the same code
 * in both places.
 *
 *     npm run build:nova-specific-days
 */
export default defineConfig({
    root: fileURLToPath(new URL('.', import.meta.url)),
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('../../resources/js', import.meta.url)),
        },
    },
    css: {
        postcss: {},
    },
    define: {
        'process.env.NODE_ENV': JSON.stringify('production'),
    },
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        lib: {
            entry: fileURLToPath(
                new URL('./resources/js/field.js', import.meta.url),
            ),
            name: 'NovaSpecificDaysField',
            formats: ['iife'],
            fileName: () => 'js/field.js',
            cssFileName: 'css/field',
        },
        rollupOptions: {
            external: ['vue', 'laravel-nova'],
            output: {
                globals: {
                    vue: 'Vue',
                    'laravel-nova': 'LaravelNova',
                },
            },
        },
    },
});
