<script setup lang="ts">
import type { PlaylistLayout } from '@/types/global';

interface Props {
    layout: PlaylistLayout;
}

const props = defineProps<Props>();
</script>

<template>
    <!--
        The embed was rebuilt into a clean iframe when the feature was saved,
        so it is shown as stored. The width is still forced here because a
        feature saved before that holds Bandcamp's own 350px until it is
        saved again, and a playlist left empty draws nothing at all.
    -->
    <div
        v-if="props.layout.attributes.playlist"
        class="w-full py-4 [&>iframe]:w-full!"
        v-html="props.layout.attributes.playlist"
    ></div>
</template>

<style scoped></style>
