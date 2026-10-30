<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { Article, Magazine } from '@/types/global';
import Advert from '../components/GOP/Advert.vue';
import Favicon from '../components/GOP/Favicon.vue';
import Hero from '../components/GOP/Hero.vue';
import Interviews from '../components/GOP/Interviews.vue';
import Subscribe from '../components/GOP/Subscribe.vue';
import DefaultLayout from '../layouts/DefaultLayout.vue';

interface Props {
    issue: Magazine;
    title: string;
    featured: Article[];
    features: Article[];
    reviews: Article[];
}

const props = defineProps<Props>();
</script>

<template>
    <Head>
        <title>{{ props.title }}</title>
        <Favicon />
    </Head>
    <DefaultLayout>
        <div class="md:w-full md:pl-48">
            <Hero :issue="props.issue" />
            <Interviews
                title="Features."
                :featured="props.featured"
                :features="props.features"
            />
            <Advert />

            <Subscribe />
        </div>
    </DefaultLayout>
</template>

<style scoped></style>
