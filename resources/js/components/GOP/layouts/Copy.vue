<script setup lang="ts">
import type { CopyLayout } from '@/types/global';

interface Props {
    layout: CopyLayout;
}

const props = defineProps<Props>();
</script>

<template>
    <!--
      rich-text marks HTML an editor wrote, wherever this layout is used.
      Tailwind's preflight strips headings back to body text and no editor
      can reach those tags, so app.css restyles them through this class.
    -->
    <div class="rich-text" v-html="props.layout.attributes.text"></div>
</template>

<style scoped></style>
