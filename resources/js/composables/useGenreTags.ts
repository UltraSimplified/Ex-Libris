import { router } from '@inertiajs/vue3';
import type { InjectionKey, Ref } from 'vue';
import { computed, inject, provide } from 'vue';

/**
 * What the tag on a listing does on a page that can narrow its own listings
 * to a genre: where it points, and how it narrows them without leaving.
 */
export type GenreTags = {
    href: (slug: string) => string;
    choose: (slug: string) => void;
};

const genreTagsKey: InjectionKey<Ref<GenreTags | null>> = Symbol('genreTags');

/**
 * Called by a page of listings with a row of genres over them, so the tags
 * on its rows choose from that row. Null while it has no such row.
 */
export function provideGenreTags(tags: Ref<GenreTags | null>): void {
    provide(genreTagsKey, tags);
}

/**
 * The tag on a listing. On a page that narrows its own listings, it narrows
 * them to its genre where they are; anywhere else, a feature or the
 * Dashboard, it goes to everything coming up in that genre.
 */
export function useGenreTag(slug: Ref<string>) {
    const pageTags = inject(genreTagsKey, null);

    const href = computed<string>(
        () =>
            pageTags?.value?.href(slug.value) ??
            `/listings/?genre=${encodeURIComponent(slug.value)}`,
    );

    /**
     * A click meant for a new tab or window is left to the browser.
     */
    const follow = (event: MouseEvent): void => {
        if (
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey
        ) {
            return;
        }

        event.preventDefault();

        if (pageTags?.value) {
            pageTags.value.choose(slug.value);
        } else {
            router.visit(href.value);
        }
    };

    return { href, follow };
}
