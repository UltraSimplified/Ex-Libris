<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { Listing } from '@/types/global';
import Advert from '../components/GOP/Advert.vue';
import Favicon from '../components/GOP/Favicon.vue';
import ListItem from '../components/GOP/ListItem.vue';
import Subscribe from '../components/GOP/Subscribe.vue';
import DefaultLayout from '../layouts/DefaultLayout.vue';

interface Props {
    title: string;
    listing: Listing;
    /**
     * Framed in the Office, where the point is to read this one listing
     * before releasing it. The site's own header, footer, advert and
     * mailing list are all left off: with them the frame is a working
     * copy of the whole site sitting inside the editor.
     */
    preview?: boolean;
}

const props = withDefaults(defineProps<Props>(), { preview: false });
</script>

<template>
    <Head>
        <title>{{ props.title }}</title>
        <meta property="og:title" :content="props.title" />
        <Favicon />
    </Head>

    <ListItem
        v-if="props.preview"
        :title="props.title"
        :listing="props.listing"
        preview
    />

    <DefaultLayout v-else>
        <div class="md:w-full md:pl-48">
            <ListItem :title="props.title" :listing="props.listing" />
            <Advert />
            <Subscribe />
        </div>
    </DefaultLayout>
</template>
