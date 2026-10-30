<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdvertLineUp } from '@/composables/useAdvertLineUp';
import type { Advert as AdvertRecord } from '@/types/global';
import Advert from '../../components/GOP/Advert.vue';
import ListingAdvert from '../../components/GOP/ListingAdvert.vue';
import Subscribe from '../../components/GOP/Subscribe.vue';
import DefaultLayout from '../../layouts/DefaultLayout.vue';

interface Stockist {
    id: number;
    name: string;
    link: string | null;
    address: string | null;
    town: string | null;
    postcode: string | null;
}

const props = defineProps<{
    stockists: Stockist[];
    title: string;
}>();

const page = usePage();
const advertLineUp = useAdvertLineUp();

/*
 * The stockists as they are laid out: an advert from the live line-up after
 * every so many of them, though never as the last row. A stockist is a
 * line where a listing is a panel, so they break half as often as the
 * listings do.
 */
type Row =
    | { kind: 'stockist'; key: string; stockist: Stockist }
    | { kind: 'advert'; key: string; advert: AdvertRecord };

const rows = computed<Row[]>(() => {
    const every = (page.props.advertEvery ?? 0) * 2;
    const lineUp = advertLineUp.value;
    const laidOut: Row[] = [];
    let slot = 0;

    props.stockists.forEach((stockist, index) => {
        laidOut.push({
            kind: 'stockist',
            key: `stockist-${stockist.id}`,
            stockist,
        });

        const due = every > 0 && (index + 1) % every === 0;

        if (due && lineUp.length > 0 && index + 1 < props.stockists.length) {
            laidOut.push({
                kind: 'advert',
                key: `advert-${slot}`,
                advert: lineUp[slot % lineUp.length],
            });
            slot++;
        }
    });

    return laidOut;
});

const whereabouts = (stockist: Stockist): string =>
    [stockist.address, stockist.town, stockist.postcode]
        .filter(Boolean)
        .join(', ');
</script>

<template>
    <Head :title="title" />
    <DefaultLayout>
        <div class="md:pl-48">
            <div class="border-b border-silver px-5 py-5 pt-4 pb-4 md:border-l">
                <h1 class="text-4xl font-semibold">{{ title }}</h1>
            </div>

            <!-- Each stockist's address goes on a line of its own beneath its name. -->
            <div class="border-silver pt-5 pb-10 md:border-l">
                <template v-for="row in rows" :key="row.key">
                    <ListingAdvert
                        v-if="row.kind === 'advert'"
                        :advert="row.advert"
                        class="my-5 border-y border-silver"
                    />
                    <p v-else class="px-5 pb-3 text-3xl tracking-tight">
                        <a
                            v-if="row.stockist.link"
                            class="link-writer font-bold transition-colors hover:text-gray-600 dark:hover:text-gray-400"
                            target="_blank"
                            rel="nofollow noreferrer"
                            :href="row.stockist.link"
                            >{{ row.stockist.name }}</a
                        >
                        <span v-else>{{ row.stockist.name }}</span>
                        <span
                            v-if="whereabouts(row.stockist)"
                            class="block text-2xl"
                            >{{ whereabouts(row.stockist) }}</span
                        >
                    </p>
                </template>
            </div>
            <Advert />
            <Subscribe />
        </div>
    </DefaultLayout>
</template>
