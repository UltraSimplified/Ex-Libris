<script setup lang="ts">
import { InfiniteScroll } from '@inertiajs/vue3';
import { computed } from 'vue';
import FeatureCard from '@/components/GOP/FeatureCard.vue';
import ListingAdvert from '@/components/GOP/ListingAdvert.vue';
import { useAdvertLineUp } from '@/composables/useAdvertLineUp';
import type { Advert, Article } from '@/types/global';

/**
 * Every feature the lead panel leaves out, six at a time - two rows of
 * three - in a panel of its own, the next six coming in as the reader
 * nears the foot of the last. An advert from the live line-up goes between
 * each six and the next, never after the last, as the listings break for
 * one. The panel takes the page's own light or dark surface.
 *
 * The address is left alone as pages come in, so a reader who follows a
 * feature and comes back is not sent to the middle of the list.
 */
interface Props {
    more: { data: Article[] };
}

const props = defineProps<Props>();
const advertLineUp = useAdvertLineUp();

/** As many as the server sends in one step of the scroll. */
const PAGE_SIZE = 6;

type Row =
    | { kind: 'features'; key: string; features: Article[] }
    | { kind: 'advert'; key: string; advert: Advert };

const rows = computed<Row[]>(() => {
    const features = props.more.data;
    const lineUp = advertLineUp.value;
    const laidOut: Row[] = [];

    for (let start = 0; start < features.length; start += PAGE_SIZE) {
        const page = start / PAGE_SIZE;

        laidOut.push({
            kind: 'features',
            key: `features-${page}`,
            features: features.slice(start, start + PAGE_SIZE),
        });

        if (start + PAGE_SIZE < features.length && lineUp.length > 0) {
            laidOut.push({
                kind: 'advert',
                key: `advert-${page}`,
                advert: lineUp[page % lineUp.length],
            });
        }
    }

    return laidOut;
});
</script>

<template>
    <section
        v-if="props.more.data.length"
        class="more-features border-t border-silver bg-surface md:border-l"
        aria-label="More features"
    >
        <InfiniteScroll data="more" only-next preserve-url>
            <template v-for="row in rows" :key="row.key">
                <div
                    v-if="row.kind === 'features'"
                    class="grid gap-x-8 gap-y-15 px-5 py-10 md:grid-cols-3"
                >
                    <FeatureCard
                        v-for="feature in row.features"
                        :key="feature.id"
                        :feature="feature"
                    />
                </div>
                <ListingAdvert
                    v-else
                    :advert="row.advert"
                    class="border-y border-silver"
                />
            </template>

            <template #loading>
                <div
                    class="grid gap-x-8 gap-y-15 px-5 pb-10 md:grid-cols-3"
                    aria-hidden="true"
                >
                    <div v-for="n in 3" :key="n" class="animate-pulse">
                        <div class="aspect-3/2 rounded bg-current/10"></div>
                        <div
                            class="mt-4 h-7 w-24 rounded-sm bg-current/10"
                        ></div>
                        <div class="mt-5 h-6 w-3/4 rounded bg-current/10"></div>
                    </div>
                </div>
            </template>
        </InfiniteScroll>
    </section>
</template>
