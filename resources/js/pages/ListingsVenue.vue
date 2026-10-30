<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { Listing, Venue } from '@/types/global';
import Favicon from '../components/GOP/Favicon.vue';
import ListingsVenueIndex from '../components/GOP/ListingsVenueIndex.vue';
import Promo from '../components/GOP/Promo.vue';
import Subscribe from '../components/GOP/Subscribe.vue';
import DefaultLayout from '../layouts/DefaultLayout.vue';

interface Props {
    title: string;
    listings: Listing[];
    venue: Venue;
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
            <ListingsVenueIndex
                :title="props.title"
                :listings="props.listings"
                :venue="props.venue"
            />
            <Promo title="Paid Advertisement" />
            <Subscribe />
        </div>
    </DefaultLayout>
</template>
