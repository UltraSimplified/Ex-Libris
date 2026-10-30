import { usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

/**
 * Listing ids this visitor has saved: on their account when signed in, in a
 * cookie the server keeps when not.
 *
 * Module level on purpose: a listings page draws a heart per row, and every
 * one of them has to agree about what is saved, including the row the visitor
 * just clicked somewhere else on the page.
 */
const saved = reactive(new Set<number>());

/** Ids with a request in flight, so a double click cannot race itself. */
const inFlight = reactive(new Set<number>());

/**
 * Laravel encrypts the CSRF token into a cookie and accepts it back as a
 * header. Nothing else in this app posts outside Inertia, so we read it here
 * rather than pulling in a client just for two endpoints.
 */
function csrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export function useBookmarks() {
    const page = usePage();

    /**
     * The server is the authority: every visit re-seeds the set from the
     * shared prop, which also picks up bookmarks made in another tab.
     */
    watch(
        () => page.props.bookmarks,
        (ids) => {
            saved.clear();
            (ids ?? []).forEach((id: number) => saved.add(id));
        },
        { immediate: true },
    );

    const signedIn = computed(() => Boolean(page.props.auth?.user));

    /** How many events are saved, so pages can hide what would be empty. */
    const count = computed((): number => saved.size);

    const isBookmarked = (id: number): boolean => saved.has(id);

    const isBusy = (id: number): boolean => inFlight.has(id);

    /**
     * Fill or empty the heart. The change lands on screen first and is undone
     * again if the request fails, so a saved event never quietly isn't.
     */
    async function toggle(id: number): Promise<void> {
        if (inFlight.has(id)) {
            return;
        }

        const wanted = !saved.has(id);

        inFlight.add(id);
        if (wanted) {
            saved.add(id);
        } else {
            saved.delete(id);
        }

        try {
            const response = await fetch(`/listings/${id}/bookmark`, {
                method: wanted ? 'POST' : 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Bookmark request failed: ${response.status}`);
            }

            const state = await response.json();

            if (state.bookmarked) {
                saved.add(id);
            } else {
                saved.delete(id);
            }
        } catch (error) {
            if (wanted) {
                saved.delete(id);
            } else {
                saved.add(id);
            }
            console.error(error);
        } finally {
            inFlight.delete(id);
        }
    }

    return { signedIn, count, isBookmarked, isBusy, toggle };
}
