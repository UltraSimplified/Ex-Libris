<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import SoldOutFlash from '@/components/GOP/SoldOutFlash.vue';
import { formatListingDates } from '@/lib/listingDates';
import type { BookmarkedListing } from '@/types/global';

/**
 * A bookmarked listing at its smallest: its picture, with its title and date
 * alongside, and all of it a link to the event. The image is expected already
 * resolved, a placeholder standing in where it has none.
 */
defineProps<{ listing: BookmarkedListing }>();
</script>

<template>
    <Link
        :href="`/listings/${listing.id}/${listing.slug}`"
        class="flex items-center gap-3 rounded-lg p-2 transition-colors duration-150 hover:bg-[#f7f7f7] active:bg-[#f7f7f7] dark:hover:bg-white/5 dark:active:bg-white/5"
    >
        <span class="relative size-24 shrink-0 overflow-hidden rounded">
            <img
                :src="listing.image"
                alt=""
                loading="lazy"
                class="size-full object-cover"
            />
            <SoldOutFlash v-if="listing.sold_out" />
        </span>

        <span class="min-w-0">
            <span class="block leading-snug font-semibold">
                {{ listing.title }}
            </span>
            <span
                class="mt-1 block font-din text-xs font-bold text-tyre uppercase"
            >
                {{ formatListingDates(listing) }}
            </span>
        </span>
    </Link>
</template>
