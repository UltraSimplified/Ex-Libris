<script setup lang="ts">
import { computed } from 'vue';
import type { SpacerLayout } from '@/types/global';

interface Props {
    layout: SpacerLayout;
}

const props = defineProps<Props>();

/**
 * The height is bound rather than interpolated: Vue reads {{ }} in text,
 * never inside an attribute, so a mustache in a style string reaches the
 * browser as the literal characters and is thrown away.
 *
 * A spacer saved before a size was picked falls back rather than
 * collapsing to nothing, which would read as the layout having failed.
 */
const height = computed<string>(
    () => props.layout.attributes.padding || '40px',
);
</script>

<template>
    <div aria-hidden="true" :style="{ height }"></div>
</template>

<style scoped></style>
