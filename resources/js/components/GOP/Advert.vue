<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useAdImpression } from '@/composables/useAdImpression';

import type { User } from '@/types';
import type { Advert } from '@/types/global';

declare module '@inertiajs/core' {
    interface PageProps {
        name: string;
        /** The sponsorship for this page's panel, or null when none is live. */
        advert: Advert | null;
        cdnUrl: string;
        auth: { user: User | null };
    }
}

const page = usePage();

const advert = computed(() => page.props.advert);
const cdnUrl = computed(() => page.props.cdnUrl);

/**
 * Prefix a stored asset path with the CDN base URL.
 * Handles any leading slash on the stored path so we
 * never end up with a double slash in the src.
 */
const cdn = (path: string): string =>
    `${cdnUrl.value}/${path.replace(/^\//, '')}`;

/**
 * Where the click came from, so the report can say which page sent it.
 */
const clickUrl = (variant: 'mobile' | 'desktop'): string =>
    `/ad/${advert.value?.id}/click?${new URLSearchParams({
        v: variant,
        p: 'panel',
        from: page.url.split('?')[0],
    })}`;

const sponsor = computed(
    () => advert.value?.owner?.trim() || advert.value?.title || '',
);

const mobileImg = ref<HTMLElement | null>(null);
const desktopImg = ref<HTMLElement | null>(null);

useAdImpression(
    computed(() => advert.value?.id ?? null),
    { mobile: mobileImg, desktop: desktopImg },
    'panel',
);
</script>

<!--
    The sponsorship panel, at the foot of every page and always shown: a live
    sponsorship in turn with the others, or, with none live, Good on Paper
    asking for one.

    Neither creative is ever drawn larger than it was uploaded, and both sit
    against the band's 20px left gutter, under the label: the 728×300
    desktop one and the 400×300 mobile one alike. The Office requires both of
    a sponsorship; one saved before that, missing either, leaves the band out
    at that size rather than showing an empty one.
-->
<template>
    <div
        v-if="advert && (advert.mobile_image || advert.desktop_image)"
        class="sponsorship-panel h-full border-silver bg-gray-200 bg-[url('@/../img/rectangle.png')] bg-cover bg-center pb-10 md:border-l"
        :class="{
            'max-md:hidden': !advert.mobile_image,
            'md:hidden': !advert.desktop_image,
        }"
    >
        <p class="px-5 py-5 text-xs text-tarmac">Sponsored by {{ sponsor }}</p>
        <a
            v-if="advert.mobile_image"
            :href="clickUrl('mobile')"
            target="_blank"
            rel="sponsored nofollow noopener"
            ><img
                ref="mobileImg"
                :src="cdn(advert.mobile_image)"
                :alt="advert.alt_text ?? advert.title"
                class="mx-5 mb-8 w-[calc(100%-2.5rem)] max-w-[400px] md:hidden"
            />
        </a>
        <a
            v-if="advert.desktop_image"
            :href="clickUrl('desktop')"
            target="_blank"
            rel="sponsored nofollow noopener"
            ><img
                ref="desktopImg"
                :src="cdn(advert.desktop_image)"
                :alt="advert.alt_text ?? advert.title"
                class="mx-5 w-[calc(100%-2.5rem)] max-w-[728px] max-md:hidden"
            />
        </a>
    </div>
    <!--
        Good on Paper's own advert, standing in for a sponsorship. The
        designer's artwork is 728×300, the desktop size; it is the only
        version, so on a phone it is drawn narrower, never wider.
    -->
    <div
        v-else
        class="sponsorship-panel sponsorship-house h-full border-silver bg-gray-200 bg-[url('@/../img/rectangle.png')] bg-cover bg-center pb-10 md:border-l"
    >
        <p class="px-5 py-5 text-xs text-tarmac">Sponsorship</p>
        <Link href="/advertise" class="block">
            <img
                src="@/../img/sponsor-good-on-paper.gif"
                alt="Sponsor Good on Paper: sponsor your local neighbourhood arts magazine"
                width="728"
                height="300"
                class="mx-5 h-auto w-[calc(100%-2.5rem)] max-w-[728px]"
            />
        </Link>
    </div>
</template>
