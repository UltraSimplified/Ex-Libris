<script setup lang="ts">
/**
 * A flash across an event's image saying it has sold out.
 *
 * Laid over the image rather than written beside it: somebody scanning a
 * page of events reads the pictures, and a line of text under the title
 * is exactly what gets missed. Set at an angle so it reads as a stamp
 * over the listing rather than part of it.
 *
 * On a list's thumbnail it runs the width of the image. On the listing's
 * own, much larger picture the same band would cut the photograph in half,
 * so `corner` folds it across the top left corner instead, at the size the
 * thumbnails use.
 *
 * The parent has to be positioned; every image this sits on already is.
 * For `corner` it has to be the image's own box, so the band is clipped
 * at the picture's edge.
 */
defineProps<{
    corner?: boolean;
}>();
</script>

<template>
    <div
        v-if="corner"
        class="pointer-events-none absolute inset-0 overflow-hidden rounded"
    >
        <span
            class="absolute top-12 -left-12 w-56 -rotate-45 bg-tarmac/90 py-1.5 text-center font-din text-sm font-bold tracking-[0.2em] text-hivis uppercase shadow-lg md:text-base"
        >
            Sold out
        </span>
    </div>
    <div
        v-else
        class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-hidden"
    >
        <span
            class="w-[140%] -rotate-12 bg-tarmac/90 py-1.5 text-center font-din text-sm font-bold tracking-[0.2em] text-hivis uppercase shadow-lg md:text-base"
        >
            Sold out
        </span>
    </div>
</template>
