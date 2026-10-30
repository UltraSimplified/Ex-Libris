<script setup lang="ts">
import type * as Leaflet from 'leaflet';
import type { Map as LeafletMap } from 'leaflet';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * A street map with a pin on the venue.
 *
 * Leaflet, its stylesheet and the tiles are fetched only here, the first
 * time a venue's page is opened, so no other page carries them. The tiles
 * are OpenStreetMap's, which ask for nothing but the credit in the corner.
 * The map does not take the scroll wheel, so scrolling the page past it
 * scrolls the page.
 *
 * A click or tap on the map, its pin or the expand button opens it over
 * the whole window, where there is room to look around: that one takes
 * the scroll wheel, and Close or Escape puts it away again. A drag is
 * not a click, so panning the small map does not open it.
 */
interface Props {
    latitude: number;
    longitude: number;
    name: string;
}

const props = defineProps<Props>();
const container = ref<HTMLElement | null>(null);
const fullContainer = ref<HTMLElement | null>(null);
const expanded = ref(false);
let leaflet: typeof Leaflet | null = null;
let map: LeafletMap | null = null;
let fullMap: LeafletMap | null = null;

const draw = (element: HTMLElement, scrollWheelZoom: boolean): LeafletMap => {
    const L = leaflet as typeof Leaflet;
    const place: [number, number] = [props.latitude, props.longitude];

    const drawn = L.map(element, { scrollWheelZoom }).setView(place, 16);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(drawn);

    // Drawn rather than an image, so there is no marker picture for the
    // bundler to lose, and in the site's hi-vis.
    L.circleMarker(place, {
        radius: 10,
        weight: 3,
        color: '#343434',
        fillColor: '#fced3c',
        fillOpacity: 1,
    })
        .addTo(drawn)
        .bindTooltip(props.name);

    return drawn;
};

const closeOnEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        close();
    }
};

const open = async (): Promise<void> => {
    if (expanded.value || !leaflet) {
        return;
    }

    expanded.value = true;
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', closeOnEscape);

    await nextTick();

    if (fullContainer.value) {
        fullMap = draw(fullContainer.value, true);
    }
};

const close = (): void => {
    fullMap?.remove();
    fullMap = null;
    expanded.value = false;
    document.body.style.overflow = '';
    window.removeEventListener('keydown', closeOnEscape);
};

onMounted(async () => {
    const [module] = await Promise.all([
        import('leaflet'),
        import('leaflet/dist/leaflet.css'),
    ]);

    leaflet = module.default;

    if (!container.value) {
        return;
    }

    map = draw(container.value, false);
    map.on('click', open);
});

onBeforeUnmount(() => {
    close();
    map?.remove();
    map = null;
});
</script>

<template>
    <!--
        isolate keeps Leaflet's own stacking inside the map, so its panes
        cannot rise over the header as it slides down.
    -->
    <div class="relative isolate">
        <div
            ref="container"
            class="h-36 w-full cursor-pointer rounded border border-silver md:h-40"
            role="img"
            :aria-label="`Map showing where ${props.name} is`"
        ></div>
        <button
            type="button"
            class="absolute top-2 right-2 z-[1000] flex size-8 cursor-pointer items-center justify-center rounded border border-[#343434] bg-white text-[#343434] shadow hover:bg-[#fced3c]"
            :aria-label="`Open the map of ${props.name} full size`"
            @click="open"
        >
            <svg
                viewBox="0 0 24 24"
                class="size-4"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7" />
            </svg>
        </button>

        <Teleport to="body">
            <div
                v-if="expanded"
                class="fixed inset-0 z-[70] flex flex-col bg-surface"
                role="dialog"
                aria-modal="true"
                :aria-label="`Map showing where ${props.name} is`"
            >
                <div
                    class="flex items-center justify-between gap-4 border-b border-silver px-4 py-3"
                >
                    <h2 class="truncate text-sm font-bold uppercase">
                        {{ props.name }}
                    </h2>
                    <button
                        type="button"
                        class="cursor-pointer text-sm font-bold uppercase underline underline-offset-2"
                        @click="close"
                    >
                        Close
                    </button>
                </div>
                <div ref="fullContainer" class="isolate w-full flex-1"></div>
            </div>
        </Teleport>
    </div>
</template>
