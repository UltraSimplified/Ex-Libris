import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import { initializeTheme } from './composables/useAppearance';
import { trackPreviousPage } from './composables/usePreviousPage';

/*
 * The brand as it should read in a browser tab. Deliberately not APP_NAME:
 * that is "GoodOnPaper", which is fine as an identifier and wrong as a
 * label, and it also seeds the session cookie name - renaming it would sign
 * everyone out.
 */
const brand = 'Good on Paper';

createInertiaApp({
    title: (title) => (title ? `${brand} — ${title}` : brand),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        delay: 0,
        color: '#4B5563',
    },
});

trackPreviousPage();

// This will set light / dark mode on page load...
initializeTheme();
