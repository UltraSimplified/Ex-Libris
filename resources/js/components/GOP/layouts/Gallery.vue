<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import type { GalleryLayout } from '@/types/global';

interface Props {
    layout: GalleryLayout;
}

const props = defineProps<Props>();
const page = usePage();

const images = computed(() => props.layout.attributes.images ?? []);
const current = ref(0);
const shown = computed(() => images.value[current.value]);
const strip = ref<HTMLElement | null>(null);

/*
 * How the photo last changed. An arrow, an arrow key or a swipe slides the
 * next photo in from that side, as though the photos were laid in a row;
 * a thumbnail jumps anywhere in the set, so its photo fades in instead.
 */
type Turn = 'next' | 'previous' | 'fade';
const turn = ref<Turn>('fade');

function url(path: string): string {
    return page.props.cdnUrl + '/' + path;
}

function show(index: number, how: Turn = 'fade'): void {
    const count = images.value.length;

    turn.value = how;
    current.value = ((index % count) + count) % count;
}

function previous(): void {
    show(current.value - 1, 'previous');
}

function next(): void {
    show(current.value + 1, 'next');
}

function preload(index: number): void {
    const image = images.value.at(index % images.value.length);

    if (image) {
        new Image().src = url(image.image);
    }
}

/*
 * A swipe across the photo turns it, on a phone or with a pen. Shorter
 * than this and it was a tap.
 */
const swipeThreshold = 40;
let swipeStartX: number | null = null;

function startSwipe(event: PointerEvent): void {
    swipeStartX = event.pointerType === 'mouse' ? null : event.clientX;
}

function endSwipe(event: PointerEvent): void {
    if (swipeStartX === null) {
        return;
    }

    const distance = event.clientX - swipeStartX;
    swipeStartX = null;

    if (Math.abs(distance) >= swipeThreshold) {
        if (distance < 0) {
            next();
        } else {
            previous();
        }
    }
}

/*
 * The strip follows along, and the photos either side are fetched ahead
 * so the next one is there when it is asked for; a thumbnail's photo is
 * fetched as the pointer reaches it. The strip is scrolled by hand,
 * because scrollIntoView would also scroll the page.
 */
watch(current, async (index) => {
    preload(index - 1);
    preload(index + 1);

    await nextTick();

    const thumb = strip.value?.children[index] as HTMLElement | undefined;

    if (strip.value && thumb) {
        strip.value.scrollTo({
            left:
                thumb.offsetLeft -
                (strip.value.clientWidth - thumb.clientWidth) / 2,
            behavior: 'smooth',
        });
    }
});
</script>

<template>
    <!--
        One photo at a time, fitted inside a 3:2 frame so the page stays put
        as portrait and landscape photos come and go, with the strip of
        thumbnails underneath. Tints are taken from the text colour, so the
        gallery sits on any of the feature colourways. No inner divs: the
        feature page pads every div nested in a layout.

        Each photo is a layer filling the frame, so the photo going and the
        photo coming can be in it at once while they slide or fade. The
        frame is isolated so neither layer ever rises over the arrows.
    -->
    <section
        v-if="shown"
        class="gallery w-full py-4"
        aria-roledescription="carousel"
        aria-label="Photojournal"
        :data-turn="turn"
    >
        <figure
            class="relative touch-pan-y outline-none select-none"
            tabindex="0"
            @keydown.left.prevent="previous"
            @keydown.right.prevent="next"
            @pointerdown="startSwipe"
            @pointerup="endSwipe"
        >
            <span
                class="gallery-frame relative isolate block aspect-3/2 overflow-hidden rounded-lg bg-current/5"
            >
                <Transition :name="`gallery-${turn}`">
                    <span
                        :key="shown.image"
                        class="absolute inset-0 flex items-center justify-center"
                    >
                        <img
                            :src="url(shown.image)"
                            :alt="shown.alt ?? ''"
                            :width="shown.width ?? undefined"
                            :height="shown.height ?? undefined"
                            draggable="false"
                            class="max-h-full w-auto max-w-full object-contain"
                        />
                    </span>
                </Transition>
            </span>

            <template v-if="images.length > 1">
                <button
                    type="button"
                    class="absolute top-[calc(50%-1.5rem)] left-2 flex size-10 cursor-pointer items-center justify-center rounded-full bg-black/50 text-2xl text-white transition hover:bg-black/75"
                    aria-label="Previous photo"
                    @click="previous"
                >
                    &lsaquo;
                </button>
                <button
                    type="button"
                    class="absolute top-[calc(50%-1.5rem)] right-2 flex size-10 cursor-pointer items-center justify-center rounded-full bg-black/50 text-2xl text-white transition hover:bg-black/75"
                    aria-label="Next photo"
                    @click="next"
                >
                    &rsaquo;
                </button>
            </template>

            <figcaption
                class="flex items-baseline justify-between gap-4 pt-2 pl-2 text-sm"
            >
                <span>{{ shown.caption }}</span>
                <span
                    v-if="images.length > 1"
                    class="shrink-0 font-din text-[10px] tracking-[.25em]"
                    aria-live="polite"
                    >{{ current + 1 }} / {{ images.length }}</span
                >
            </figcaption>
        </figure>

        <ul
            v-if="images.length > 1"
            ref="strip"
            class="relative mt-3 flex gap-2 overflow-x-auto pb-2"
        >
            <li
                v-for="(image, index) in images"
                :key="image.image"
                class="shrink-0"
            >
                <button
                    type="button"
                    class="block cursor-pointer overflow-hidden rounded opacity-50 transition hover:opacity-100 aria-[current=true]:opacity-100 aria-[current=true]:outline-2 aria-[current=true]:-outline-offset-2 aria-[current=true]:outline-current"
                    :aria-label="`Show photo ${index + 1}`"
                    :aria-current="index === current ? 'true' : undefined"
                    @click="show(index)"
                    @pointerenter="preload(index)"
                    @focus="preload(index)"
                >
                    <img
                        :src="url(image.thumb)"
                        alt=""
                        loading="lazy"
                        width="96"
                        height="64"
                        class="h-16 w-24 object-cover"
                    />
                </button>
            </li>
        </ul>
    </section>
</template>

<style scoped>
/*
 * A slide carries both photos the full width of the frame: the one going
 * leaves by one side as the one coming arrives from the other.
 */
.gallery-next-enter-active,
.gallery-next-leave-active,
.gallery-previous-enter-active,
.gallery-previous-leave-active {
    transition: transform 0.5s cubic-bezier(0.22, 0.61, 0.36, 1);
}

.gallery-next-enter-from,
.gallery-previous-leave-to {
    transform: translateX(100%);
}

.gallery-next-leave-to,
.gallery-previous-enter-from {
    transform: translateX(-100%);
}

/*
 * A fade brings the new photo in over the old. The new one gains its
 * opacity quickly and the old loses it slowly, so the frame behind barely
 * shows through between them.
 */
.gallery-fade-enter-active {
    z-index: 1;
    transition: opacity 0.4s ease-out;
}

.gallery-fade-leave-active {
    transition: opacity 0.4s ease-in;
}

.gallery-fade-enter-from,
.gallery-fade-leave-to {
    opacity: 0;
}

/*
 * Nobody who has asked for less motion is slid anywhere: every change is
 * a short fade.
 */
@media (prefers-reduced-motion: reduce) {
    .gallery-next-enter-active,
    .gallery-next-leave-active,
    .gallery-previous-enter-active,
    .gallery-previous-leave-active {
        transition: opacity 0.2s ease;
    }

    .gallery-next-enter-from,
    .gallery-next-leave-to,
    .gallery-previous-enter-from,
    .gallery-previous-leave-to {
        transform: none;
        opacity: 0;
    }
}
</style>
