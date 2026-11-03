<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CalendarDays, LayoutGrid, Rows3 } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import BookmarkedListingActions from '@/components/GOP/BookmarkedListingActions.vue';
import BookmarkedListingCompact from '@/components/GOP/BookmarkedListingCompact.vue';
import BookmarkedListingsCalendar from '@/components/GOP/BookmarkedListingsCalendar.vue';
import ListingRow from '@/components/GOP/ListingRow.vue';
import { useBookmarks } from '@/composables/useBookmarks';
import { resolvePlaceholders } from '@/composables/usePlaceholders.js';
import type { BookmarkedListing } from '@/types/global';

/**
 * The events an account has saved that have not finished, on the Dashboard,
 * in whichever of three layouts the reader last chose on this device: each as
 * the listings pages show it with what can be done with it beneath, or a
 * narrow grid of pictures and titles, or months of dates.
 *
 * Without the other panels' padding, as a row brings its own and the row's
 * hover has to reach the panel's edges.
 */
const props = defineProps<{ listings: BookmarkedListing[] }>();

const page = usePage();
const { isBookmarked } = useBookmarks();

const layouts = [
    { value: 'full', label: 'Full', icon: Rows3 },
    { value: 'compact', label: 'Compact', icon: LayoutGrid },
    { value: 'calendar', label: 'Calendar', icon: CalendarDays },
] as const;

type Layout = (typeof layouts)[number]['value'];

const LAYOUT_KEY = 'bookmarked-listings-layout';

const layout = ref<Layout>('full');

onMounted(() => {
    const stored = localStorage.getItem(LAYOUT_KEY);

    if (layouts.some(({ value }) => value === stored)) {
        layout.value = stored as Layout;
    }
});

watch(layout, (chosen) => localStorage.setItem(LAYOUT_KEY, chosen));

const resolvedListings = computed(
    () =>
        resolvePlaceholders(
            props.listings,
            page.props.cdnUrl,
        ) as BookmarkedListing[],
);

/**
 * The full layout keeps a row whose heart has just been emptied, so it can be
 * filled again. The other two have no heart, and a date picked out for an
 * event no longer saved would be wrong, so they go by what is saved now.
 */
const savedListings = computed(() =>
    resolvedListings.value.filter(({ id }) => isBookmarked(id)),
);
</script>

<template>
    <!--
        At least a screen tall, so that My Bookmarks can bring even one short
        listing to the top of the window. The largest measure of a phone's
        screen, with its toolbars away, so there is room either way.
    -->
    <div class="min-h-lvh">
        <section
            id="bookmarked-listings"
            class="dashboard-panel scroll-mt-4 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <h2 class="px-5 pt-6 pb-1 text-xl font-semibold">
                Bookmarked listings.
            </h2>

            <fieldset class="px-5 pt-3 pb-4">
                <legend class="sr-only">Show bookmarked listings as</legend>
                <div
                    class="inline-flex flex-wrap gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800"
                >
                    <label
                        v-for="{ value, label, icon } in layouts"
                        :key="value"
                        class="flex cursor-pointer items-center gap-1.5 rounded-md px-3 py-1.5 text-sm transition-colors has-focus-visible:outline-2 has-focus-visible:outline-foreground"
                        :class="
                            layout === value
                                ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                                : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60'
                        "
                    >
                        <input
                            v-model="layout"
                            type="radio"
                            name="bookmarked-listings-layout"
                            :value="value"
                            class="sr-only"
                        />
                        <component
                            :is="icon"
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {{ label }}
                    </label>
                </div>
            </fieldset>

            <ul
                v-if="layout === 'full'"
                class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
            >
                <li v-for="listing in resolvedListings" :key="listing.id">
                    <ListingRow :listing="listing" />
                    <BookmarkedListingActions :listing="listing" />
                </li>
            </ul>

            <p v-else-if="!savedListings.length" class="px-5 pb-6">
                Nothing bookmarked at the moment.
            </p>

            <div v-else-if="layout === 'compact'" class="@container px-3 pb-4">
                <ul class="grid gap-x-2 gap-y-1 @xl:grid-cols-2">
                    <li v-for="listing in savedListings" :key="listing.id">
                        <BookmarkedListingCompact :listing="listing" />
                    </li>
                </ul>
            </div>

            <BookmarkedListingsCalendar v-else :listings="savedListings" />
        </section>
    </div>
</template>
