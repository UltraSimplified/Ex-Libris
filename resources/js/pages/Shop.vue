<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { category as categoryRoute } from '@/actions/App/Http/Controllers/ShopController';
import Advert from '@/components/GOP/Advert.vue';
import Subscribe from '@/components/GOP/Subscribe.vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';

/** A shelf in the shop with something on sale on it. */
interface ShopCategory {
    name: string;
    slug: string;
    description: string | null;
    image: string | null;
    products_count: number;
}

defineProps<{
    title: string;
    categories: ShopCategory[];
}>();

const page = usePage();
</script>

<template>
    <Head :title="title" />
    <DefaultLayout>
        <div class="md:w-full md:pl-48">
            <div class="border-b border-silver px-5 py-5 pt-4 pb-4 md:border-l">
                <h1 class="text-4xl font-semibold">{{ title }}</h1>
            </div>

            <div
                v-if="categories.length"
                class="flex flex-wrap items-start gap-8 border-silver px-5 py-8 md:border-l"
            >
                <Link
                    v-for="category in categories"
                    :key="category.slug"
                    :href="categoryRoute.url(category.slug)"
                    class="group block w-full sm:w-90"
                    prefetch
                >
                    <div class="relative">
                        <img
                            v-if="category.image"
                            :src="`${page.props.cdnUrl}/${category.image}`"
                            :alt="category.name"
                            class="block h-auto w-full transition-opacity duration-300 group-hover:opacity-90"
                        />
                    </div>
                    <!-- The cover is the card's width, its height whatever its shape gives. -->
                    <div>
                        <h2 class="mt-4 text-2xl font-bold tracking-tight">
                            {{ category.name }}
                        </h2>
                        <p v-if="category.description" class="mt-1">
                            {{ category.description }}
                        </p>
                    </div>
                </Link>
            </div>

            <p v-else class="border-silver px-5 py-10 text-2xl md:border-l">
                Nothing in the shop just yet. Check back soon.
            </p>

            <Advert />
            <Subscribe />
        </div>
    </DefaultLayout>
</template>
