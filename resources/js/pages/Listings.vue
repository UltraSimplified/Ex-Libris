<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Advert from '@/components/GOP/Advert.vue';
import type { Category, Listing, Promoter, Venue } from '@/types/global';
import Favicon from '../components/GOP/Favicon.vue';
import ListingsIndex from '../components/GOP/ListingsIndex.vue';
import Subscribe from '../components/GOP/Subscribe.vue';
import DefaultLayout from '../layouts/DefaultLayout.vue';

interface Props {
    title: string;
    listings: Listing[];
    all: boolean;
    today: boolean;
    tomorrow: boolean;
    thisWeek: boolean;
    nextWeek: boolean;
    bookmarked?: boolean;
    venue: Venue;
    promoter?: Promoter | null;
    genres?: Category[];
}

const props = defineProps<Props>();
</script>

<template>
    <Head>
        <title>{{ props.title }}</title>
        <meta property="og:title" :content="props.title" />
        <Favicon />
    </Head>
    <DefaultLayout>
        <div class="md:w-full md:pl-48">
            <ListingsIndex
                :title="props.title"
                :listings="props.listings"
                :all="props.all"
                :today="props.today"
                :tomorrow="props.tomorrow"
                :thisWeek="props.thisWeek"
                :nextWeek="props.nextWeek"
                :bookmarked="props.bookmarked"
                :venue="props.venue"
                :promoter="props.promoter"
                :genres="props.genres"
            />

            <Advert />
            <Subscribe />
        </div>
    </DefaultLayout>
</template>
