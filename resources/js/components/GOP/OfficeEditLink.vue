<script setup lang="ts">
import { PencilSquareIcon } from '@heroicons/vue/24/outline';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * A pencil beside a listing's bookmark heart, for the office's own
 * accounts, that opens the listing in the Office's editor. Everybody else
 * is told nothing of where the Office is, so sees nothing.
 *
 * A plain link: the Office is Nova, not one of the site's own pages.
 */
interface Props {
    listingId: number;
}

const props = defineProps<Props>();
const page = usePage();

const editUrl = computed<string | null>(() => {
    const office = page.props.auth?.office;

    return office
        ? `${office}/resources/listings/${props.listingId}/edit`
        : null;
});
</script>

<template>
    <a
        v-if="editUrl"
        :href="editUrl"
        class="inline-flex shrink-0 items-center align-middle text-ink-muted transition-colors hover:text-tarmac dark:hover:text-white"
        aria-label="Edit this listing in the Office"
        title="Edit in the Office"
    >
        <PencilSquareIcon class="h-6 w-6" aria-hidden="true" />
    </a>
</template>
