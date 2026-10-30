<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ImgFullLayout } from '@/types/global';

interface Props {
    layout: ImgFullLayout;
    /**
     * Shown at no more than the picture's own size, shrinking to fit a
     * narrower column but never stretched to fill a wider one.
     */
    naturalSize?: boolean;
}

const props = withDefaults(defineProps<Props>(), { naturalSize: false });
const page = usePage();
const imageClass = computed(() =>
    props.naturalSize ? 'h-auto max-w-full rounded-lg' : 'w-full rounded-lg',
);
</script>

<template>
    <div class="md:mb-0">
        <a
            v-if="props.layout.attributes.link"
            :href="props.layout.attributes.link"
            target="_blank"
            rel="nofollow noreferrer"
        >
            <img
                :src="page.props.cdnUrl + '/' + props.layout.attributes.image"
                :class="imageClass"
                :alt="props.layout.attributes.alt"
            />
        </a>
        <img
            v-else
            :src="page.props.cdnUrl + '/' + props.layout.attributes.image"
            :class="imageClass"
            :alt="props.layout.attributes.alt"
        />
        <div
            class="-mb-6 w-full pt-2 pl-2 text-sm"
            v-if="props.layout.attributes.caption"
        >
            {{ props.layout.attributes.caption }}
        </div>
    </div>
</template>

<style scoped></style>
