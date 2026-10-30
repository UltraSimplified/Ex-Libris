import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

/*
 * Built with the application's own Vite and Vue, as the Gallery field is,
 * so the field needs no node_modules of its own. Vue and Nova's mixins are
 * the copies Nova has already loaded into the page, as window.Vue and
 * window.LaravelNova, so they are left out of the bundle.
 *
 *     npm run build:nova-video-embed
 */
export default defineConfig({
    root: fileURLToPath(new URL('.', import.meta.url)),
    plugins: [vue()],
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
            name: 'NovaVideoEmbedField',
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
