import { router } from '@inertiajs/vue3';

/*
 * The page somebody was on before this one, within the site. Inertia
 * moves between pages without the browser loading a new one, so the
 * referrer never changes; this follows each visit instead. Nothing is
 * known about where somebody was before they arrived, so the first page
 * has no previous one.
 */
let current: string | null = null;
let previous: string | null = null;

/**
 * Follow page visits from here on. Called once, as the app starts.
 */
export function trackPreviousPage(): void {
    router.on('navigate', (event) => {
        const url = event.detail.page.url;

        if (url === current) {
            return;
        }

        previous = current;
        current = url;
    });
}

/**
 * Where the page at `here` was reached from on the site, if it was.
 *
 * A page sets itself up before Inertia announces the visit to it, so
 * asked while setting up, the page last announced is still the one
 * somebody came from; asked any later, it is this one.
 */
export function previousPage(here: string): string | null {
    return current !== null && current !== here ? current : previous;
}
