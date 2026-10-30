<script setup lang="ts">
import { usePage, Link } from '@inertiajs/vue3';
import placeholder from '@/../img/listings-placeholder.webp';
import BookmarkHeart from '@/components/GOP/BookmarkHeart.vue';
import LocationPin from '@/components/GOP/LocationPin.vue';
import { formatListingDates } from '@/lib/listingDates';
import type { Listing, Venue } from '@/types/global';

interface Props {
    title: string;
    listings: Listing[];
    venue: Venue;
}

const slugify = (str: string) => {
    return str
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
};
const page = usePage();
const props = defineProps<Props>();
</script>
<template>
    <!-- LISTINGS -->

    <div class="border-b border-silver px-10 pt-8 pb-8 md:border-l">
        <h1 class="text-4xl font-semibold">{{ title }}</h1>
    </div>
    <div class="border-b border-silver p-2 pl-10 md:border-l">
        <h2 class="text-2xl"></h2>
    </div>
    <div class="border-silver p-6 md:border-l">
        <div class="grid grid-cols-1 divide-y divide-gray-200 md:grid-cols-3">
            <div
                class="px-5 py-5"
                v-for="listing in props.listings"
                :key="listing.id"
            >
                <Link :href="'/listings/' + listing.id + '/' + listing.slug">
                    <img
                        :src="
                            listing.thumbnail
                                ? page.props.cdnUrl + '/' + listing.thumbnail
                                : placeholder
                        "
                        class="w-full rounded"
                    />
                </Link>

                <div class="mt-4 flex items-center gap-3">
                    <button
                        class="rounded-full bg-tarmac px-4 py-2 text-sm font-bold text-white uppercase md:text-xs"
                    >
                        {{ listing.category.name }}
                    </button>

                    <BookmarkHeart :listing-id="listing.id" />
                </div>
                <h2
                    class="mt-4 font-din text-sm font-bold uppercase md:text-xs"
                >
                    {{ formatListingDates(listing) }}
                </h2>
                <h1 class="mt-4 text-2xl">{{ listing.title }}</h1>
                <h2
                    class="mt-4 mb-0 font-din text-sm text-gray-600 uppercase md:text-xs"
                >
                    <LocationPin class="float-left mr-2 h-3 w-3" />
                    <Link
                        :href="
                            '/listings/venue/' +
                            listing.venue.id +
                            '/' +
                            slugify(listing.venue.name)
                        "
                        >{{ listing.venue.name }}</Link
                    >
                </h2>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
