<script setup lang="ts">
import type { Colourway } from '@/types/global';

interface Props {
    layout: 'divider';
    colourway?: Colourway;
}

const props = withDefaults(defineProps<Props>(), {
    colourway: 'white',
});

/**
 * Map each colourway to a complete Tailwind border class.
 *
 * Slate is the only dark background, so it gets a light border;
 * the three light backgrounds share the dark slate border.
 * Full class names are used so Tailwind's scanner generates them.
 */
const borderClass: Record<Colourway, string> = {
    white: 'border-slate',
    'hi-vis': 'border-slate',
    'key-lime': 'border-slate',
    slate: 'border-silver',
};
</script>

<template>
    <hr :class="borderClass[props.colourway] + ` mb-5`" />
</template>

<style scoped></style>
