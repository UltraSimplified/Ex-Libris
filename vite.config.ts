import { wayfinder } from '@laravel/vite-plugin-wayfinder';
// @ts-ignore
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
// @ts-ignore
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    server: {
       watch: {
           ignored: ['**/storage/**'],
	},
    },
    resolve: {
        alias: {
            'vue-tailwind-datepicker/dist/style.css':
                '/resources/css/empty.css',
        },
    },
});
