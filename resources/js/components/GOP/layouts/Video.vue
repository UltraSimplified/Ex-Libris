<script setup lang="ts">
import type { VideoLayout } from '@/types/global';

interface Props {
    layout: VideoLayout;
}

const props = defineProps<Props>();
</script>

<template>
    <!--
        The embed is shown as pasted from YouTube or Vimeo. YouTube fixes the
        iframe at 560 by 315 and Vimeo wraps it in a padded box, so either way
        it is stretched to the column here and kept at 16:9. A video left
        empty draws nothing at all.
    -->
    <div
        v-if="props.layout.attributes.video"
        class="w-full py-4 [&_iframe]:aspect-video [&_iframe]:h-auto! [&_iframe]:w-full!"
        v-html="props.layout.attributes.video"
    ></div>
</template>

<style scoped></style>
