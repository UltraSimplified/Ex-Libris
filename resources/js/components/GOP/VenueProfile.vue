<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import VenueMap from '@/components/GOP/VenueMap.vue';
import type { Venue } from '@/types/global';

/**
 * The top of a venue's page of listings, under its title: its cover photo,
 * set as a feature's image is, what the Office has written about it, then
 * where it is - the address beside a street map. Each part is left out
 * when the venue has nothing for it.
 */
interface Props {
    venue: Venue;
}

const props = defineProps<Props>();
const page = usePage();

const coverUrl = computed<string | null>(() =>
    props.venue.cover_photo
        ? `${page.props.cdnUrl}/${props.venue.cover_photo.replace(/^\//, '')}`
        : null,
);

const phone = computed<string>(() => (props.venue.phone ?? '').trim());
const email = computed<string>(() => (props.venue.email ?? '').trim());

const addressLines = computed<string[]>(() =>
    [props.venue.address, props.venue.town, props.venue.postcode]
        .map((line) => (line ?? '').trim())
        .filter(Boolean),
);

const placed = computed<boolean>(
    () => props.venue.latitude !== null && props.venue.longitude !== null,
);
</script>

<template>
    <section class="venue-profile" :aria-label="props.venue.name">
        <!--
            Set as a feature's image is, inset and rounded, filling the
            column up to 666px and no wider.
        -->
        <div v-if="coverUrl" class="border-silver px-5 pt-5 md:border-l">
            <img
                :src="coverUrl"
                :alt="props.venue.name"
                class="block h-auto w-full max-w-[666px] rounded-md"
            />
        </div>

        <!-- Held to the picture's 666px, as the address and map are. -->
        <div
            v-if="props.venue.description"
            class="border-silver px-5 pt-8 pb-2 md:border-l"
        >
            <div
                class="rich-text max-w-[666px] text-lg md:text-base"
                v-html="props.venue.description"
            ></div>
        </div>

        <!--
            Held to the picture's 666px, so the map's right edge never runs
            past the picture's.
        -->
        <div
            v-if="addressLines.length || phone || email || placed"
            class="border-b border-silver px-5 py-8 md:border-l"
        >
            <div
                class="grid max-w-[666px] gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] md:gap-8"
            >
                <address
                    v-if="addressLines.length || phone || email"
                    class="font-din text-sm uppercase not-italic md:text-xs"
                >
                    <span
                        v-for="line in addressLines"
                        :key="line"
                        class="block leading-6"
                        >{{ line }}</span
                    >
                    <a
                        v-if="props.venue.website_link ?? props.venue.url"
                        :href="props.venue.website_link ?? props.venue.url"
                        target="_blank"
                        rel="noopener"
                        class="mt-4 inline-block underline underline-offset-2"
                        >Website</a
                    >
                    <a
                        v-if="phone"
                        :href="`tel:${phone.replace(/[^\d+]/g, '')}`"
                        class="mt-4 block leading-6 underline underline-offset-2"
                        >{{ phone }}</a
                    >
                    <a
                        v-if="email"
                        :href="`mailto:${email}`"
                        :class="[
                            'block leading-6 break-all normal-case underline underline-offset-2',
                            phone ? '' : 'mt-4',
                        ]"
                        >{{ email }}</a
                    >
                </address>
                <VenueMap
                    v-if="placed"
                    :latitude="props.venue.latitude as number"
                    :longitude="props.venue.longitude as number"
                    :name="props.venue.name"
                />
            </div>
        </div>
    </section>
</template>
