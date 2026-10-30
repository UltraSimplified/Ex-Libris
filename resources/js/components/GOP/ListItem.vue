<script setup lang="ts">
import { usePage, Link } from '@inertiajs/vue3';
import { Megaphone } from '@lucide/vue';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import BookmarkHeart from '@/components/GOP/BookmarkHeart.vue';
import ListingLineUp from '@/components/GOP/ListingLineUp.vue';
import LocationPin from '@/components/GOP/LocationPin.vue';
import OfficeEditLink from '@/components/GOP/OfficeEditLink.vue';
import PlaceholderFlash from '@/components/GOP/PlaceholderFlash.vue';
import SoldOutFlash from '@/components/GOP/SoldOutFlash.vue';
import { resolvePlaceholders } from '@/composables/usePlaceholders.js';
import { previousPage } from '@/composables/usePreviousPage';
import { formatListingDates } from '@/lib/listingDates';
import type { GalleryImage, LineUpArtist, Listing } from '@/types/global';

interface Props {
    title: string;
    listing: Listing;
    /**
     * Framed in the Office rather than read on the site. The listing is
     * shown on its own: nothing that would carry somebody out of the
     * frame and into a browsable copy of the site inside the editor.
     */
    preview?: boolean;
}

const page = usePage();
const props = withDefaults(defineProps<Props>(), { preview: false });
const lightboxOpen = ref(false);
const currentIndex = ref(0);
const openLightbox = (index: number) => {
    currentIndex.value = index;
    lightboxOpen.value = true;
};

const slugify = (str: string) => {
    return str
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
};
const escapeHtml = (text: string): string =>
    text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

/**
 * The description as typed: a blank line starts a paragraph and a single
 * one breaks the line. Text pasted from Windows or a word processor ends
 * its lines with \r\n, which is read the same way, and anything that looks
 * like markup is shown as typed rather than run.
 */
/*
 * Back to wherever the listing was opened from - a venue, a category, a
 * search - by stepping back through history, so the page comes back with
 * its search and scroll as they were. Opened from outside the site, there
 * is nothing on it to go back to, so it goes to the listings.
 */
const cameFrom = previousPage(page.url);
const backHref = cameFrom ?? '/listings/';

const goBack = (event: MouseEvent): void => {
    if (!cameFrom || event.metaKey || event.ctrlKey || event.shiftKey) {
        return;
    }

    event.preventDefault();
    window.history.back();
};

/**
 * The listing's own name, wherever it appears whole in its description,
 * so the act stands out from what is said about them. Matched on the
 * escaped text, and only between word boundaries, so a name inside a
 * longer word is left alone.
 */
const emboldenTitle = (escaped: string): string => {
    const title = escapeHtml(props.listing.title?.trim() ?? '');

    if (title.length < 3) {
        return escaped;
    }

    const pattern = new RegExp(
        `(?<![\\p{L}\\p{N}])${title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(?![\\p{L}\\p{N}])`,
        'giu',
    );

    return escaped.replace(pattern, (name) => `<strong>${name}</strong>`);
};

const formattedBody = computed(() => {
    if (!props.listing.body) return '';
    return props.listing.body
        .replace(/\r\n?/g, '\n')
        .split(/\n[ \t]*\n\s*/)
        .map((paragraph) => paragraph.trim())
        .filter(Boolean)
        .map(
            (paragraph) =>
                `<p>${emboldenTitle(escapeHtml(paragraph)).replace(/\n/g, '<br />')}</p>`,
        )
        .join('');
});

/**
 * The artists on the bill, from the line-up as it is stored: in Nova's
 * Flexible shape, as a JSON string or already decoded, like the gallery.
 */
const lineUp = computed<LineUpArtist[]>(() => {
    const stored = props.listing.line_up;
    const groups: { attributes?: Partial<LineUpArtist> }[] =
        typeof stored === 'string' ? JSON.parse(stored) : (stored ?? []);

    return groups
        .map((group) => ({
            name: group.attributes?.name ?? '',
            playlist: group.attributes?.playlist ?? null,
            video: group.attributes?.video ?? null,
        }))
        .filter((artist) => artist.name && (artist.playlist || artist.video));
});

/**
 * Typed outright, because JSON.parse returns `any` and an `any` swallows
 * the rest of the union: the template's v-for then cannot tell an array
 * from an object, and takes `index` for a string key.
 */
const gallery = computed<GalleryImage[]>(() => {
    if (!props.listing.gallery) return [];
    if (typeof props.listing.gallery === 'string') {
        return JSON.parse(props.listing.gallery);
    }
    return props.listing.gallery;
});
const closeLightbox = () => {
    lightboxOpen.value = false;
};
const handleKeydown = (e: KeyboardEvent) => {
    if (!lightboxOpen.value) return;
    if (e.key === 'ArrowRight' && currentIndex.value < gallery.value.length - 1)
        currentIndex.value++;
    if (e.key === 'ArrowLeft' && currentIndex.value > 0) currentIndex.value--;
    if (e.key === 'Escape') closeLightbox();
};
onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));

const formattedDates = computed(() => formatListingDates(props.listing));

/**
 * Listings with resolved image paths — CDN-prefixed where a thumbnail exists,
 * or a deterministic local placeholder where it does not.
 */
const resolvedListings = computed(() =>
    resolvePlaceholders([props.listing], page.props.cdnUrl),
);
</script>

<template>
    <div
        v-if="!props.preview"
        class="border-b border-silver p-2 pl-5 md:border-l"
    >
        <h2 class="text-2xl">
            <Link :href="backHref" @click="goBack">&#x27C0; back</Link>
        </h2>
    </div>

    <!--
        The left column stretches to the row's full height, so its rules run
        down to whatever follows even when the right column is the taller;
        the right column keeps to the top so it can stick. The left column
        gives up width before the right drops below 15rem, rather than
        squeezing the details into a sliver at tablet widths.
    -->
    <div class="flex flex-col md:flex-row">
        <!-- Left column: image + body (body visible on desktop only) -->
        <div
            class="flex w-full flex-col md:max-w-160 md:min-w-0 md:basis-160 md:border-r md:border-l md:border-silver"
        >
            <!-- Image -->
            <div class="relative px-5 py-5">
                <div class="relative">
                    <img
                        :src="resolvedListings[0].image"
                        class="w-full rounded"
                    />
                    <SoldOutFlash v-if="props.listing.sold_out" corner />
                </div>
                <PlaceholderFlash
                    v-if="props.preview && !props.listing.thumbnail"
                />
            </div>

            <!-- Body text: desktop only -->
            <div class="hidden border-t border-silver md:block">
                <div class="space-y-4 px-5 py-6" v-html="formattedBody" />
            </div>

            <ListingLineUp
                v-if="lineUp.length"
                :artists="lineUp"
                inset
                class="hidden md:block"
            />
        </div>

        <!-- Right column: sticky metadata -->
        <div
            class="flex min-w-0 flex-col px-6 py-5 md:sticky md:top-0 md:min-w-60 md:flex-1 md:self-start"
        >
            <!-- Categories -->
            <div class="flex items-center gap-3">
                <button
                    class="rounded-sm bg-tarmac px-4 py-2 text-sm font-bold text-white uppercase md:text-xs"
                >
                    {{ props.listing.category.name }}
                </button>

                <BookmarkHeart :listing-id="props.listing.id" />
                <OfficeEditLink :listing-id="props.listing.id" />
            </div>

            <!-- Date -->
            <h2 class="mt-4 font-din text-sm font-bold uppercase md:text-xs">
                {{ formattedDates }}
            </h2>

            <!-- Title -->
            <h1 class="mt-4 text-2xl font-semibold">
                {{ props.listing.title }}
            </h1>

            <!-- Venue, and under it who is putting the event on -->
            <div class="mt-4 border-t border-b border-silver pt-4 pb-4">
                <h2
                    class="flex items-center gap-2 font-din text-sm text-gray-600 uppercase md:text-xs"
                >
                    <LocationPin class="h-3 w-3 dark:text-tyre" />
                    <Link
                        v-if="!props.preview"
                        :href="
                            '/listings/venue/' +
                            listing.venue.id +
                            '/' +
                            slugify(listing.venue.name)
                        "
                        class="dark:text-silver"
                        >{{ listing.venue.name }}</Link
                    >
                    <span v-else class="dark:text-silver">{{
                        listing.venue.name
                    }}</span>
                </h2>
                <h2
                    v-if="listing.promoter"
                    class="mt-2 flex items-center gap-2 font-din text-sm text-gray-600 uppercase md:text-xs"
                >
                    <Megaphone
                        class="size-3 shrink-0 dark:text-tyre"
                        aria-hidden="true"
                    />
                    <Link
                        v-if="!props.preview"
                        :href="
                            '/listings/promoter/' +
                            listing.promoter.id +
                            '/' +
                            slugify(listing.promoter.name)
                        "
                        class="dark:text-silver"
                        >{{ listing.promoter.name }}</Link
                    >
                    <span v-else class="dark:text-silver">{{
                        listing.promoter.name
                    }}</span>
                </h2>
            </div>

            <div
                v-if="listing.event_time_description?.trim()"
                class="mt-2 flex gap-2"
            >
                Time: {{ listing.event_time_description }}
            </div>
            <div v-if="listing.ticket_price" class="mt-2 flex gap-2">
                Tickets: {{ listing.ticket_price }}
            </div>
            <div class="mt-2 flex gap-2">
                <a
                    :href="
                        props.listing.ticket_link ??
                        props.listing.event_link ??
                        props.listing.venue.url
                    "
                    target="_blank"
                    noreferrer
                    nofollow
                >
                    <button
                        class="text-tyr hover:dark:outline-solid-2 mt-4 mb-0 w-fit cursor-pointer rounded-full bg-hivis p-2 px-4 py-2 pr-4 font-din text-sm font-bold text-slate uppercase hover:bg-hivis hover:text-tarmac md:text-xs dark:text-tarmac hover:dark:bg-black hover:dark:text-hivis hover:dark:outline-offset-1 hover:dark:outline-hivis"
                    >
                        {{ props.listing.button_text }}
                    </button>
                </a>
            </div>

            <!-- Body text: mobile only, appears after buy tickets -->
            <div class="mt-6 border-t border-silver pt-4 md:hidden">
                <div class="space-y-4 py-4" v-html="formattedBody" />
            </div>

            <ListingLineUp
                v-if="lineUp.length"
                :artists="lineUp"
                class="md:hidden"
            />
            <div
                v-if="gallery && gallery.length"
                class="mt-6 border-t border-silver pt-4"
            >
                <p class="mb-4 font-din text-sm font-bold uppercase md:text-xs">
                    LOOK
                </p>

                <!-- Grid -->
                <div class="grid grid-cols-5 gap-2">
                    <div
                        v-for="(image, index) in gallery"
                        :key="image.key"
                        class="aspect-square cursor-pointer overflow-hidden rounded"
                        @click="openLightbox(index)"
                    >
                        <img
                            :src="
                                page.props.cdnUrl + '/' + image.attributes.image
                            "
                            :alt="
                                image.attributes.caption || props.listing.title
                            "
                            class="h-full w-full object-cover transition-opacity hover:opacity-80"
                        />
                    </div>
                </div>

                <!-- Lightbox -->
                <Teleport to="body">
                    <Transition name="fade">
                        <div
                            v-if="lightboxOpen"
                            class="fixed inset-0 z-50 flex items-center justify-center bg-black/90"
                            @click.self="closeLightbox"
                        >
                            <!-- Close button -->
                            <button
                                class="absolute top-4 right-4 text-2xl font-bold text-white"
                                @click="closeLightbox"
                            >
                                &times;
                            </button>

                            <!-- Prev button -->
                            <button
                                v-if="currentIndex > 0"
                                class="absolute left-4 px-4 py-2 text-4xl font-bold text-white"
                                @click="currentIndex--"
                            >
                                &#8249;
                            </button>

                            <!-- Image -->
                            <div class="max-h-screen max-w-5xl px-16 py-8">
                                <img
                                    :src="
                                        page.props.cdnUrl +
                                        '/' +
                                        gallery[currentIndex].attributes.image
                                    "
                                    :alt="
                                        gallery[currentIndex].attributes
                                            .caption || props.listing.title
                                    "
                                    class="max-h-[80vh] max-w-full object-contain"
                                />
                                <p
                                    v-if="
                                        gallery[currentIndex].attributes.caption
                                    "
                                    class="mt-2 text-center text-sm text-gray-300"
                                >
                                    {{
                                        gallery[currentIndex].attributes.caption
                                    }}
                                </p>
                            </div>

                            <!-- Next button -->
                            <button
                                v-if="currentIndex < gallery.length - 1"
                                class="absolute right-4 px-4 py-2 text-4xl font-bold text-white"
                                @click="currentIndex++"
                            >
                                &#8250;
                            </button>
                        </div>
                    </Transition>
                </Teleport>
            </div>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
