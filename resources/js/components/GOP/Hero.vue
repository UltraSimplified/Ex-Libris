<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import type { Magazine } from '@/types/global';

interface Props {
    issue: Magazine;
}

const props = defineProps<Props>();
const page = usePage();
</script>

<template>
    <div
        class="flex-none border-b md:w-full md:border-l md:border-silver lg:flex"
    >
        <div>
            <img
                :src="page.props.cdnUrl + '/' + props.issue.hero_image"
                width="800"
                height="1000"
                fetchpriority="high"
                class="w-full max-w-125"
            />
        </div>
        <div
            class="w-full border-silver bg-white p-15 pb-10 text-black md:w-fit md:pl-30 dark:bg-surface dark:text-white"
        >
            <h1
                class="text-center font-haas text-6xl font-semibold md:text-left"
            >
                Issue {{ props.issue.issue_number }}.<br />Out Now.<br />
            </h1>
            <p
                class="font-500 py-5 text-center text-2xl leading-6 md:text-left"
            >
                Subscribe to receive <br />a copy in the post <br/>or in your inbox
            </p>
            <p class="mt-10 flex justify-center md:flex-none md:justify-normal">
                <Link
                    href="/subscribe"
                    class="font-500 inline-block rounded bg-tarmac px-8 py-2 font-haas text-white hover:bg-blue-500 dark:bg-silver dark:hover:bg-hivis dark:hover:text-black"
                    data-test="hero-subscribe"
                >
                    Subscribe
                </Link>
            </p>
        </div>
    </div>
</template>

<style scoped></style>
