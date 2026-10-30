<script setup lang="ts">
import type { LineUpArtist } from '@/types/global';

/**
 * The artists on a listing's bill, in order, each named and followed by
 * their playlist and then their video. Every player is shown, so a reader
 * can scroll the bill and play the one they are curious about; the players
 * load only as they come near the screen.
 *
 * The embeds are rebuilt by the server's EmbedSanitiser before they are
 * stored, so they are drawn as they come.
 */
const props = defineProps<{
    artists: LineUpArtist[];
    /** Inset from the page edge, as the desktop column is. */
    inset?: boolean;
}>();
</script>

<template>
    <div class="listing-line-up">
        <section
            v-for="(artist, index) in props.artists"
            :key="`${index}-${artist.name}`"
            class="line-up-artist border-t border-silver pt-4"
            :class="props.inset ? 'px-5' : 'mt-6'"
        >
            <h3 class="pl-2 font-din text-[12px] tracking-[.25em] uppercase">
                {{ artist.name }}
            </h3>
            <!-- Bandcamp, Spotify, Tidal or Qobuz player -->
            <div
                v-if="artist.playlist"
                class="w-full py-4"
                v-html="artist.playlist"
            />
            <div
                v-if="artist.video"
                class="w-full py-4 [&>iframe]:aspect-video [&>iframe]:h-auto [&>iframe]:w-full [&>iframe]:rounded-md"
                v-html="artist.video"
            />
        </section>
    </div>
</template>
