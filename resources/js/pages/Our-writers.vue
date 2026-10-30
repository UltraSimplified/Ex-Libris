<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Advert from '../components/GOP/Advert.vue';
import Subscribe from '../components/GOP/Subscribe.vue';
import DefaultLayout from '../layouts/DefaultLayout.vue';

defineProps({
    writers: Object,
    title: String,
});
</script>

<template>
    <Head :title="title" />
    <DefaultLayout>
        <div class="md:w-full md:pl-48">
            <div class="border-b border-silver px-5 py-5 pt-4 pb-4 md:border-l">
                <h1 class="text-4xl font-semibold">{{ title }}</h1>
            </div>

            <h2
                class="border-silver px-5 py-5 pt-5 text-2xl font-bold md:border-l"
            >
                <template v-for="writer in writers" :key="writer.id"
                    ><Link
                        class="link-writer uppercase"
                        :href="`/our-writers/${writer.slug}`"
                        ><span>{{ writer.name }}</span>
                        &nbsp;&ndash;&nbsp;
                    </Link>
                </template>
            </h2>
            <Advert />
            <Subscribe />
        </div>
    </DefaultLayout>
</template>

<style scoped></style>
