<script setup lang="ts">
// Head is imported rather than assumed: it is not globally registered here,
// so a page that only uses the tag silently keeps the default title.
import { Head, Link } from '@inertiajs/vue3';
import Advert from '../components/GOP/Advert.vue';
import Subscribe from '../components/GOP/Subscribe.vue';
import DefaultLayout from '../layouts/DefaultLayout.vue';
</script>

<template>
    <Head title="Page not found" />
    <DefaultLayout>
        <div class="md:w-full md:pl-48">
            <div class="border-silver px-5 py-10 text-center md:border-l">
                <img
                    src="/img/boredom.webp"
                    alt="We're Good on Paper — here to save the universe from the evil forces of boredom"
                    class="mx-auto mb-8 w-full max-w-md"
                />

                <h1 class="mx-auto mb-6 max-w-lg text-5xl font-bold">
                    404 — this page doesn't exist
                </h1>

                <p class="mx-auto mb-8 max-w-lg text-2xl text-neutral-500">
                    We've saved you from the evil forces of boredom, but not
                    from a dead link.
                </p>

                <p>
                    <Link href="/" class="link-writer font-bold uppercase"
                        >Back to Good on Paper</Link
                    >
                    &nbsp;&ndash;&nbsp;
                    <Link
                        href="/listings"
                        class="link-writer font-bold uppercase"
                        >Browse the listings</Link
                    >
                </p>
            </div>

            <Advert />
            <Subscribe />
        </div>
    </DefaultLayout>
</template>
