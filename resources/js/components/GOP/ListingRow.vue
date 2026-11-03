<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Megaphone } from '@lucide/vue';
import BookmarkHeart from '@/components/GOP/BookmarkHeart.vue';
import LocationPin from '@/components/GOP/LocationPin.vue';
import OfficeEditLink from '@/components/GOP/OfficeEditLink.vue';
import SoldOutFlash from '@/components/GOP/SoldOutFlash.vue';
import { formatListingDates } from '@/lib/listingDates';
import type { Listing } from '@/types/global';

/**
 * One listing as the listings pages show it, and as a feature shows the
 * event it is about. The listing's image is expected already resolved, a
 * placeholder standing in where it has none.
 *
 * Brief drops the price and the promoter and closes up what is left, for a
 * list of events the reader has already chosen rather than is choosing from.
 * Beside the image, the venue ends level with the picture's bottom edge
 * however long the title runs.
 */
interface Props {
    listing: Listing;
    brief?: boolean;
}

const props = defineProps<Props>();

const slugify = (str: string) => {
    return str
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
};
</script>

<template>
    <!--
        The whole of a listing's row is a way into it: the link on the title
        is stretched over the row, and the tag, the heart and the venue are
        lifted above it so they still go where they say.
    -->
    <div
        class="relative px-5 py-5 transition-colors duration-150 hover:bg-[#f7f7f7] active:bg-[#f7f7f7] has-[[aria-pressed]:active]:bg-transparent md:flex md:gap-8 dark:hover:bg-white/5 dark:active:bg-white/5"
        :class="props.brief ? 'md:items-stretch' : 'md:items-start'"
    >
        <!-- Image: full width on mobile, fixed column on desktop -->

        <div class="relative md:w-44 md:shrink-0">
            <img :src="props.listing.image" alt="" class="w-full rounded" />
            <SoldOutFlash v-if="props.listing.sold_out" />
        </div>

        <!-- Content column -->
        <div
            class="min-w-0 md:flex-1"
            :class="{ 'md:flex md:flex-col': props.brief }"
        >
            <div class="mt-4 flex items-center gap-3 md:mt-0">
                <Link
                    :href="'/listings/category/' + props.listing.category.slug"
                    class="relative z-10 rounded-sm bg-tarmac px-4 py-2 font-din text-sm text-white uppercase hover:bg-hivis hover:text-tarmac md:text-xs"
                >
                    {{ props.listing.category.name }}
                </Link>

                <BookmarkHeart
                    :listing-id="props.listing.id"
                    class="relative z-10"
                />
                <OfficeEditLink
                    :listing-id="props.listing.id"
                    class="relative z-10"
                />
            </div>

            <h2
                class="mt-2 font-din text-sm font-bold text-tyre uppercase md:text-xs"
            >
                {{ formatListingDates(props.listing) }}
            </h2>

            <h1 class="mt-0 text-2xl" :class="{ 'leading-7': props.brief }">
                <Link
                    :href="
                        '/listings/' +
                        props.listing.id +
                        '/' +
                        props.listing.slug
                    "
                    class="after:absolute after:inset-0"
                    >{{ props.listing.title }}</Link
                >
            </h1>
            <h2
                v-if="props.listing.event_time_description?.trim()"
                class="mt-0 font-din text-sm font-bold text-tyre uppercase md:text-xs"
            >
                Time: {{ props.listing.event_time_description }}
            </h2>
            <h2
                v-if="props.listing.ticket_price && !props.brief"
                class="mt-0 font-din text-sm font-bold text-tyre uppercase md:text-xs"
            >
                Price: {{ props.listing.ticket_price }}
            </h2>
            <!--
                The venue, and who is putting the event on where anyone is:
                side by side a tab apart where there is room, the promoter
                under the venue on a phone.
            -->
            <h2
                class="mb-0 flex flex-col font-din text-sm text-tyre uppercase md:flex-row md:items-center md:gap-6 md:text-xs"
                :class="props.brief ? 'mt-0 md:mt-auto' : 'mt-4'"
            >
                <Link
                    :href="
                        '/listings/venue/' +
                        props.listing.venue.id +
                        '/' +
                        slugify(props.listing.venue.name)
                    "
                    class="relative z-10 flex w-fit items-center gap-2 p-2 pr-4 hover:rounded-lg hover:bg-hivis hover:text-tarmac"
                    :class="{ '-ml-2 md:py-0.5': props.brief }"
                >
                    <LocationPin class="h-4 w-3 shrink-0" />
                    {{ props.listing.venue.name }}
                </Link>
                <Link
                    v-if="props.listing.promoter && !props.brief"
                    :href="
                        '/listings/promoter/' +
                        props.listing.promoter.id +
                        '/' +
                        slugify(props.listing.promoter.name)
                    "
                    class="relative z-10 flex w-fit items-center gap-2 p-2 pr-4 hover:rounded-lg hover:bg-hivis hover:text-tarmac"
                >
                    <Megaphone class="size-4 shrink-0" aria-hidden="true" />
                    {{ props.listing.promoter.name }}
                </Link>
            </h2>
        </div>
    </div>
</template>
