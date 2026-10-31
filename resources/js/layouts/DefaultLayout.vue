<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import BackToTop from '../components/GOP/BackToTop.vue';
import Footer from '../components/GOP/Footer.vue';
import Header from '../components/GOP/Header.vue';

defineProps({
    writers: Object,
    title: String,
    /*
     * The feature page draws its rule 2.5rem further left than the other
     * pages, to give its column the room. The footer follows it there.
     */
    featureLayout: Boolean,
});

const page = usePage();
const inMagazineSection = computed(() =>
    ['/magazine', '/who-we-are', '/advertise'].some((section) =>
        page.url.startsWith(section),
    ),
);
</script>
<template>
    <div>
        <Header :magazine-section="inMagazineSection" />
        <slot />
        <Footer :feature-layout="featureLayout" />
        <BackToTop />

    </div>
</template>
