<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    /** The embed's address with the unsaved settings in its query. */
    src: string;
}>();

/**
 * Widths a widget commonly finds itself at on somebody's site, so an owner
 * can see the layout change before pasting it anywhere.
 */
const widths = [
    { label: 'Sidebar', value: 300 },
    { label: 'Phone', value: 390 },
    { label: 'Column', value: 720 },
    { label: 'Full width', value: null },
] as const;

const width = ref<number | null>(null);
const height = ref(480);
const scroll = ref(false);
const maxWidth = ref<number | null>(null);
const frame = ref<HTMLIFrameElement | null>(null);

const origin = computed(() => new URL(props.src, window.location.href).origin);

/**
 * The widget asks for its size the same way it asks the loader on a real
 * site (resources/js/embed-frame.js), so the preview sizes it the same way.
 */
const onMessage = (event: MessageEvent): void => {
    const data = event.data;

    if (
        event.origin !== origin.value ||
        event.source !== frame.value?.contentWindow ||
        data?.type !== 'gop:size'
    ) {
        return;
    }

    height.value = Math.max(0, Math.ceil(data.height));
    scroll.value = Boolean(data.scroll);
    maxWidth.value = data.width ?? null;
};

onMounted(() => window.addEventListener('message', onMessage));
onBeforeUnmount(() => window.removeEventListener('message', onMessage));

const frameWidth = computed<string>(() => {
    const limits = [width.value, maxWidth.value].filter(
        (value): value is number => value !== null,
    );

    return limits.length ? `${Math.min(...limits)}px` : '100%';
});
</script>

<template>
    <div class="grid gap-3">
        <div
            class="flex flex-wrap items-center gap-1"
            role="group"
            aria-label="Preview width"
        >
            <button
                v-for="option in widths"
                :key="option.label"
                type="button"
                class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
                :class="
                    width === option.value
                        ? 'bg-foreground text-background'
                        : 'text-muted-foreground hover:bg-muted'
                "
                @click="width = option.value"
            >
                {{ option.label }}
            </button>
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-dashed border-sidebar-border/70 bg-[repeating-linear-gradient(45deg,transparent,transparent_8px,var(--color-muted)_8px,var(--color-muted)_9px)] p-4"
        >
            <iframe
                ref="frame"
                :src="src"
                title="Widget preview"
                class="mx-auto block border-0 bg-transparent transition-[width]"
                :style="{ width: frameWidth, height: `${height}px` }"
                :scrolling="scroll ? 'auto' : 'no'"
            />
        </div>
    </div>
</template>
