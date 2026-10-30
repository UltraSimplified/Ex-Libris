<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';

/**
 * How far down, in pixels, the reader must be before the button shows -
 * the same point on every page, whatever its length.
 */
const THRESHOLD = 800;

const visible = ref(false);

const updateVisibility = () => {
    visible.value = window.scrollY > THRESHOLD;
};

const scrollToTop = () => {
    const reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
};

onMounted(() => {
    updateVisibility();
    window.addEventListener('scroll', updateVisibility, { passive: true });
});
onUnmounted(() => window.removeEventListener('scroll', updateVisibility));
</script>

<template>
    <Transition
        enter-from-class="translate-y-3 scale-90 opacity-0!"
        leave-to-class="translate-y-2 opacity-0!"
        enter-active-class="motion-safe:transition-[opacity,translate,scale] motion-safe:duration-700 motion-safe:ease-[cubic-bezier(0.22,1,0.36,1)]"
        leave-active-class="motion-safe:transition-[opacity,translate] motion-safe:duration-300 motion-safe:ease-in"
    >
        <button
            v-show="visible"
            type="button"
            aria-label="Back to top"
            class="fixed right-5 bottom-5 z-30 flex size-14 cursor-pointer items-center justify-center rounded-full bg-hivis opacity-80 hover:opacity-100 focus:opacity-100 focus:outline-none"
            @click="scrollToTop"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="24"
                height="14"
                viewBox="0 0 24 14"
                class="overflow-visible"
            >
                <polyline
                    points="1,13 12,2 23,13"
                    fill="none"
                    stroke="#343434"
                    stroke-width="2"
                />
            </svg>
        </button>
    </Transition>
</template>
