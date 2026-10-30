<script setup lang="ts">
import { usePage, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Magazine } from '@/types/global';
import Advert from '../../components/GOP/Advert.vue';
import Favicon from '../../components/GOP/Favicon.vue';
import Subscribe from '../../components/GOP/Subscribe.vue';
import DefaultLayout from '../../layouts/DefaultLayout.vue';

interface Props {
    issues: Magazine[];
    title: string;
    year: number;
}

const page = usePage();
const props = defineProps<Props>();
const years = computed(() => {
    const current = new Date().getFullYear();
    return Array.from({ length: current - 2015 + 1 }, (_, i) => current - i);
});
</script>

<template>
    <Head>
        <title>{{ props.title }}</title>
        <meta property="og:title" :content="props.title" />
        <Favicon />
    </Head>

    <DefaultLayout>
        <div class="md:pl-48">
            <div class="border-b border-silver px-5 py-5 pt-4 pb-4 md:border-l">
                <h1 class="text-4xl font-semibold">
                    {{ title }}
                </h1>
            </div>
            <div class="border-b border-silver p-2 pl-5 md:border-l">
                <h2 class="text-l">
                    <template v-for="year in years" :key="year">
                        <strong v-if="year == props.year">{{ year }}</strong>
                        <!--
                            Kept in the page, as the sub-nav's links are,
                            and where the reader was on it, so changing the
                            year swaps the covers rather than reloading.
                        -->
                        <Link
                            v-else
                            :href="'/magazine/archives/' + year"
                            preserve-scroll
                            >{{ year }}</Link
                        >
                        <span v-if="year > 2015"> &nbsp;|&nbsp; </span>
                    </template>
                </h2>
            </div>
            <ol
                class="grid grid-cols-1 gap-6 border-b border-silver p-8 md:grid-cols-3 md:gap-8 md:border-l"
            >
                <li
                    v-for="issue in issues"
                    :key="issue.id"
                    class="flex flex-col items-center"
                >
                    <!--
                        A link only when there is a file of the issue to
                        download: one that has not been put up yet is shown
                        as its cover alone.
                    -->
                    <component
                        :is="issue.downloadable ? 'a' : 'div'"
                        :href="
                            issue.downloadable
                                ? `/download/${issue.id}`
                                : undefined
                        "
                        :title="
                            issue.downloadable
                                ? 'Click to download the magazine'
                                : undefined
                        "
                        :rel="
                            issue.downloadable
                                ? 'noreferrer nofollow'
                                : undefined
                        "
                    >
                        <img
                            :src="page.props.cdnUrl + '/' + issue.cover_photo"
                            class="w-full object-cover"
                        />
                    </component>
                    <div class="mt-4 text-center">
                        <h2 class="text-3xl">
                            Issue {{ issue.issue_number }}<br />{{
                                issue.month_year
                            }}
                        </h2>
                    </div>
                </li>
            </ol>
            <Advert />
            <Subscribe />
        </div>
    </DefaultLayout>
</template>

<style scoped></style>
