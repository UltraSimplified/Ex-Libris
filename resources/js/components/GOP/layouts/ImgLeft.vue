<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { ImgLeftLayout } from '@/types/global';

interface Props {
    layout: ImgLeftLayout;
}

const props = defineProps<Props>();
const page = usePage();
</script>

<template>
    <div class="-mb-20 md:mt-10 md:mb-0 md:flex md:gap-8 md:pr-10 md:pl-0">
        <div class="mb-5 md:mb-0 md:flex-1">
            <a
                v-if="props.layout.attributes.link"
                :href="props.layout.attributes.link"
                target="_blank"
                rel="nofollow noreferrer"
            >
                <img
                    :src="
                        page.props.cdnUrl + '/' + props.layout.attributes.image
                    "
                    class="w-full rounded-lg"
                    :alt="props.layout.attributes.alt"
                />
            </a>
            <img
                v-else
                :src="page.props.cdnUrl + '/' + props.layout.attributes.image"
                class="w-full rounded-lg"
                :alt="props.layout.attributes.alt"
            />
            <div
                class="-ml-2 w-full pt-2 pl-2 text-sm md:-mb-6"
                v-if="props.layout.attributes.caption"
            >
                {{ props.layout.attributes.caption }}
            </div>
        </div>
        <div class="mb-10 md:mb-16 md:flex-1">
            <div class="rich-text" v-html="props.layout.attributes.text"></div>
        </div>
    </div>
</template>

<style scoped></style>
