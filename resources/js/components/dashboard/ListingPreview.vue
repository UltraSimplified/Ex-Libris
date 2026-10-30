<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';

/**
 * A listing as the public will see it, in a frame over the dashboard.
 *
 * The real page rather than a second rendering of it, so what is checked
 * is what runs.
 */
const props = defineProps<{
    /** The listing being looked at. */
    id: number;
    title: string;
}>();

const emit = defineEmits<{ close: [] }>();

const closeOnEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        emit('close');
    }
};

onMounted(() => window.addEventListener('keydown', closeOnEscape));
onBeforeUnmount(() => window.removeEventListener('keydown', closeOnEscape));
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4 md:p-8"
        @click="emit('close')"
    >
        <div
            role="dialog"
            aria-modal="true"
            :aria-label="`Preview of ${props.title}`"
            class="flex h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-lg bg-background shadow-xl"
            @click.stop
        >
            <div class="flex items-center justify-between gap-4 px-4 py-3">
                <h2 class="truncate text-sm font-bold">{{ props.title }}</h2>
                <button
                    type="button"
                    class="cursor-pointer text-sm font-bold text-muted-foreground hover:text-foreground"
                    @click="emit('close')"
                >
                    Close
                </button>
            </div>

            <iframe
                :src="`/listings/${props.id}/preview`"
                :title="`Preview of ${props.title}`"
                class="w-full flex-1 border-0"
            ></iframe>
        </div>
    </div>
</template>
