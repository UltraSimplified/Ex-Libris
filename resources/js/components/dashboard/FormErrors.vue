<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        errors: Record<string, string>;
        /** What failed to save, named in the summary line. */
        subject?: string;
    }>(),
    { subject: 'listing' },
);

/**
 * Every message, including those for fields that have no error slot of their
 * own — nested gallery rows, say. Without this a single rejected field can
 * discard a whole save with nothing on screen to explain why.
 */
const messages = computed<string[]>(() => [
    ...new Set(Object.values(props.errors).filter(Boolean)),
]);
</script>

<template>
    <div
        v-if="messages.length"
        role="alert"
        class="rounded-md border border-red-600/40 bg-red-600/5 px-4 py-3"
    >
        <p class="text-sm font-medium text-red-700 dark:text-red-500">
            {{
                messages.length === 1
                    ? `Your ${subject} could not be saved:`
                    : `Your ${subject} could not be saved — ${messages.length} things need fixing:`
            }}
        </p>
        <ul
            class="mt-1 list-inside list-disc text-sm text-red-700 dark:text-red-500"
        >
            <li v-for="message in messages" :key="message">{{ message }}</li>
        </ul>
    </div>
</template>
