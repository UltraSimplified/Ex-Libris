<script setup lang="ts">
import { PencilSquareIcon } from '@heroicons/vue/24/outline';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * A pencil beside a listing's bookmark heart, or a feature's category, for
 * the office's own accounts, that opens it in the Office's editor. Everybody
 * else is told nothing of where the Office is, so sees nothing.
 *
 * A plain link: the Office is Nova, not one of the site's own pages.
 */
interface Props {
    /** The Office's name for what is edited, as its addresses have it. */
    resource: 'listings' | 'features';
    resourceId: number;
}

const props = defineProps<Props>();
const page = usePage();

const editUrl = computed<string | null>(() => {
    const office = page.props.auth?.office;

    return office
        ? `${office}/resources/${props.resource}/${props.resourceId}/edit`
        : null;
});

const label = computed<string>(() =>
    props.resource === 'features'
        ? 'Edit this feature in the Office'
        : 'Edit this listing in the Office',
);
</script>

<template>
    <a
        v-if="editUrl"
        :href="editUrl"
        class="inline-flex shrink-0 items-center align-middle text-ink-muted transition-colors hover:text-tarmac dark:hover:text-white"
        :aria-label="label"
        title="Edit in the Office"
    >
        <PencilSquareIcon class="h-6 w-6" aria-hidden="true" />
    </a>
</template>
