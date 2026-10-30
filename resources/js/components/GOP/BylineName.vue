<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Byline } from '@/types/global';

/**
 * A writer's name, as a link to their page on Our Writers when they have
 * one. An account that is not on the masthead has no page, so its name
 * stays plain text rather than a link that goes nowhere.
 *
 * The link is lifted above any link stretched over the panel it sits in,
 * so it still goes to the writer rather than the feature.
 */
interface Props {
    writer: Byline;
}

const props = defineProps<Props>();
</script>

<template>
    <Link
        v-if="props.writer.slug"
        :href="`/our-writers/${props.writer.slug}`"
        class="relative z-10 hover:underline"
        >{{ props.writer.name }}</Link
    >
    <template v-else>{{ props.writer.name }}</template>
</template>
