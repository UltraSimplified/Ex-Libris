<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import BookmarkedListingActions from '@/components/GOP/BookmarkedListingActions.vue';
import ListingRow from '@/components/GOP/ListingRow.vue';
import { resolvePlaceholders } from '@/composables/usePlaceholders.js';
import type { BookmarkedListing } from '@/types/global';

/**
 * The events an account has saved that have not finished, on the Dashboard:
 * each as the listings pages show it, with what can be done with it beneath.
 *
 * Without the other panels' padding, as a row brings its own and the row's
 * hover has to reach the panel's edges.
 */
const props = defineProps<{ listings: BookmarkedListing[] }>();

const page = usePage();

const resolvedListings = computed(
    () =>
        resolvePlaceholders(
            props.listings,
            page.props.cdnUrl,
        ) as BookmarkedListing[],
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

            <ul
                class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
            >
                <li v-for="listing in resolvedListings" :key="listing.id">
                    <ListingRow :listing="listing" />
                    <BookmarkedListingActions :listing="listing" />
                </li>
            </ul>
        </section>
    </div>
</template>
