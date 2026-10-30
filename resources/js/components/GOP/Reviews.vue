<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { Article } from '@/types/global';

interface Props {
    title: string;
    reviews: Article[];
}

defineProps<Props>();
const page = usePage();
</script>
<template>
    <!-- ALBUM REVIEWS -->
    <div class="h-fit bg-tarmac">
        <h1 class="px-5 pt-8 pb-4 text-2xl font-bold text-white md:pl-10"></h1>

        <div class="border-t-1 border-silver"></div>
        <div class="md:flex md:gap-8 md:pb-8 md:pl-5">
            <template v-for="review in reviews" :key="review.id">
                <!--
                    The whole panel is a way into the review, as a
                    feature's is; the tag is lifted above the stretched
                    link so it still goes to the rest of the reviews.
                -->
                <div class="relative bg-tarmac px-4 py-8 md:w-full">
                    <img
                        :src="page.props.cdnUrl + '/' + review.cover_photo"
                        class="w-full rounded"
                    />
                    <a
                        href="/magazine/features/reviews"
                        class="relative z-10 mt-4 inline-block rounded-sm bg-white px-4 py-2 text-sm font-bold text-black hover:bg-hivis md:text-xs"
                    >
                        REVIEWS
                    </a>
                    <h1 class="mt-5 text-2xl text-white">
                        <a
                            :href="
                                '/magazine/features/' +
                                review.id +
                                '/' +
                                review.slug +
                                '/'
                            "
                            class="after:absolute after:inset-0"
                            >{{ review.title }}</a
                        >
                    </h1>
                    <h2
                        class="mt-4 text-sm font-bold text-white uppercase md:text-xs"
                    >
                        BY {{ review.user.name }}
                    </h2>
                    <p class="mt-8 text-xl text-white md:text-sm">
                        {{ review.excerpt }}
                    </p>
                </div>
            </template>
        </div>
    </div>
</template>

<style scoped></style>
