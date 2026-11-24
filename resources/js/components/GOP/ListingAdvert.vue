<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useAdImpression } from '@/composables/useAdImpression';
import type { Advert } from '@/types/global';

/**
 * An advert from the live line-up, set among the listings as a row of its
 * own: the 728×300 desktop creative from 768px up, the 400×300 mobile one
 * below that, as the advert panel shows them, and neither ever drawn
 * larger than it was uploaded. Its impression is counted, like the
 * panel's, once it has been half in view for a second. An advert with no
 * mobile creative shows phones its desktop one, letterboxed to their width;
 * one with no desktop creative leaves the row out on desktops.
 *
 * It sits on the sponsorship panel's grey, so that on a phone it is not
 * taken for one of the listings around it.
 */
interface Props {
    advert: Advert;
}

const props = defineProps<Props>();
const page = usePage();

const cdn = (path: string): string =>
    `${page.props.cdnUrl}/${path.replace(/^\//, '')}`;

/**
 * Where the click came from, so the report can say which page sent it.
 */
const clickUrl = (variant: 'mobile' | 'desktop'): string =>
    `/ad/${props.advert.id}/click?${new URLSearchParams({
        v: variant,
        p: 'list',
        from: page.url.split('?')[0],
    })}`;

/**
 * The creative phones are shown: the mobile one, or the desktop one when the
 * advert has no mobile one. Either way it is the phones' copy, so its views
 * and clicks are counted as mobile.
 */
const phoneImage = computed(
    () => props.advert.mobile_image || props.advert.desktop_image || null,
);

const mobileImg = ref<HTMLElement | null>(null);
const desktopImg = ref<HTMLElement | null>(null);

useAdImpression(
    computed(() => props.advert.id),
    { mobile: mobileImg, desktop: desktopImg },
    'list',
);
</script>

<template>
    <div
        v-if="phoneImage"
        class="listing-advert bg-gray-200 bg-[url('@/../img/rectangle.png')] bg-cover bg-center px-5 py-5"
        :class="{ 'md:hidden': !props.advert.desktop_image }"
    >
        <!--
            Shown and hidden on the images, as the advert panel does: a
            display class on the links would outrank md:hidden and show both.
        -->
        <p class="pb-3 font-din text-xs text-tarmac uppercase">
            Paid advertisement
        </p>
        <a
            :href="clickUrl('mobile')"
            target="_blank"
            rel="sponsored nofollow noopener"
        >
            <img
                ref="mobileImg"
                :src="cdn(phoneImage)"
                :alt="props.advert.alt_text ?? props.advert.title"
                class="w-full rounded md:hidden"
                :class="
                    props.advert.mobile_image ? 'max-w-[400px]' : 'max-w-[728px]'
                "
            />
        </a>
        <a
            v-if="props.advert.desktop_image"
            :href="clickUrl('desktop')"
            target="_blank"
            rel="sponsored nofollow noopener"
        >
            <img
                ref="desktopImg"
                :src="cdn(props.advert.desktop_image)"
                :alt="props.advert.alt_text ?? props.advert.title"
                class="w-full max-w-[728px] rounded max-md:hidden"
            />
        </a>
    </div>
</template>
