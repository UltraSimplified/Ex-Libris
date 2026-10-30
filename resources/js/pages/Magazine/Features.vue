<script setup lang="ts">
import Advert from '@/components/GOP/Advert.vue';
import MoreFeatures from '@/components/GOP/MoreFeatures.vue';
import type { Article } from '@/types/global';
import Interviews from '../../components/GOP/Interviews.vue';
import Subscribe from '../../components/GOP/Subscribe.vue';
import DefaultLayout from '../../layouts/DefaultLayout.vue';

interface Props {
    title: string;
    latest: Article;
    features: Article[];
    /** Every feature the lead leaves out, paged for the infinite scroll. */
    more?: { data: Article[] };
}

const props = defineProps<Props>();
</script>
<template>
    <DefaultLayout>
        <div class="md:w-full md:pl-48">
            <Interviews
                :title="props.title"
                page-title
                :featured="props.latest ? [props.latest] : []"
                :features="props.features"
            />

            <Advert />

            <MoreFeatures v-if="props.more" :more="props.more" />

            <Subscribe />
        </div>
    </DefaultLayout>
</template>
