<script setup lang="ts">
/**
 * The festival a listing is part of, as its logo in the bottom right of
 * the listing's cover, on a square of the festival's background colour.
 *
 * Laid over the image rather than baked into it, so changing the logo, or
 * the listing leaving the festival, changes every cover at once, and the
 * picture itself stays as it was uploaded. A third of the cover's width
 * wherever the cover is drawn, so a phone's small cover keeps most of its
 * poster. Drawn only where the listing wants it and the festival has a
 * logo to show.
 *
 * Comes before the sold-out band in its cover, so the band lies over it
 * where they meet. The parent has to be the image's own positioned box.
 */
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Listing } from '@/types/global';

const props = defineProps<{
    listing: Pick<Listing, 'festival' | 'show_festival_mark'>;
}>();

const page = usePage();

const logo = computed<string | null>(() => {
    const festival = props.listing.festival;

    if (!festival?.logo || props.listing.show_festival_mark === false) {
        return null;
    }

    return `${String(page.props.cdnUrl).replace(/\/$/, '')}/${festival.logo.replace(/^\//, '')}`;
});
</script>

<template>
    <span
        v-if="logo"
        data-test="festival-mark"
        class="pointer-events-none absolute right-0 bottom-0 aspect-square w-1/3 overflow-hidden rounded-tl rounded-br p-[6%]"
        :style="{ backgroundColor: listing.festival?.mark_background }"
    >
        <img
            :src="logo"
            :alt="listing.festival?.name"
            class="size-full object-contain"
        />
    </span>
</template>
