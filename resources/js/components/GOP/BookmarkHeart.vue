<script setup lang="ts">
import { computed } from 'vue';
import { useBookmarks } from '@/composables/useBookmarks';

interface Props {
    listingId: number;
}

const props = defineProps<Props>();
const { isBookmarked, isBusy, toggle } = useBookmarks();

const bookmarked = computed(() => isBookmarked(props.listingId));
const busy = computed(() => isBusy(props.listingId));

const title = computed(() =>
    bookmarked.value
        ? 'Click to remove this bookmark'
        : 'Click to bookmark this listing',
);
</script>

<template>
    <button
        type="button"
        class="inline-flex shrink-0 cursor-pointer items-center align-middle text-tarmac transition-opacity hover:opacity-70 disabled:opacity-40 dark:text-tyre"
        :title="title"
        :aria-label="title"
        :aria-pressed="bookmarked"
        :disabled="busy"
        @click.prevent.stop="toggle(props.listingId)"
    >
        <svg
            class="h-6 w-6"
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            :fill="bookmarked ? 'currentColor' : 'none'"
            stroke="currentColor"
            stroke-width="2.75"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
        >
            <path
                d="M12 20.4 3.9 12.3a5 5 0 0 1 0-7.1 5 5 0 0 1 7.1 0l1 1 1-1a5 5 0 0 1 7.1 0 5 5 0 0 1 0 7.1Z"
            />
        </svg>
    </button>
</template>
