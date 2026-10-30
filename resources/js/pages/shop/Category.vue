<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { product as productRoute } from '@/actions/App/Http/Controllers/ShopController';
import Advert from '@/components/GOP/Advert.vue';
import ShopPrice from '@/components/GOP/ShopPrice.vue';
import Subscribe from '@/components/GOP/Subscribe.vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';

/** A product as its card shows it. Prices are in pence. */
interface ProductCard {
    name: string;
    slug: string;
    summary: string | null;
    image: string | null;
    price_from: number;
    price_to: number;
    /** Its usual price, struck through, when it is on sale. */
    struck_price: number | null;
    sold_out: boolean;
}

const props = defineProps<{
    title: string;
    category: { name: string; slug: string; description: string | null };
    products: ProductCard[];
}>();

const page = usePage();

const productUrl = (product: ProductCard): string =>
    productRoute.url({
        shopCategory: props.category.slug,
        product: product.slug,
    });
</script>

<template>
    <Head :title="title" />
    <DefaultLayout>
        <div class="md:w-full md:pl-48">
            <div class="border-b border-silver px-5 py-5 pt-4 pb-4 md:border-l">
                <h1 class="text-4xl font-semibold">{{ category.name }}</h1>
                <p v-if="category.description" class="mt-2 max-w-2xl">
                    {{ category.description }}
                </p>
            </div>

            <div
                v-if="products.length"
                class="flex flex-wrap items-start gap-8 border-silver px-5 py-8 md:border-l"
            >
                <Link
                    v-for="product in products"
                    :key="product.slug"
                    :href="productUrl(product)"
                    class="group block w-full sm:w-90"
                    prefetch
                >
                    <div class="relative">
                        <img
                            v-if="product.image"
                            :src="`${page.props.cdnUrl}/${product.image}`"
                            :alt="product.name"
                            class="block h-auto w-full transition-opacity duration-300 group-hover:opacity-90"
                            :class="{ 'opacity-50': product.sold_out }"
                        />
                        <span
                            v-if="product.sold_out"
                            class="absolute top-3 left-3 rounded-sm bg-tarmac px-3 py-1 text-sm font-bold text-white uppercase"
                            >Sold out</span
                        >
                    </div>
                    <!-- The cover is the card's width, its height whatever its shape gives. -->
                    <div>
                        <h2 class="mt-4 text-2xl font-bold tracking-tight">
                            {{ product.name }}
                        </h2>
                        <p v-if="product.summary" class="mt-1">
                            {{ product.summary }}
                        </p>
                        <p class="mt-2 text-lg font-semibold">
                            <span v-if="product.price_from !== product.price_to"
                                >From
                            </span>
                            <ShopPrice
                                :price="product.price_from"
                                :struck-price="product.struck_price"
                            />
                        </p>
                    </div>
                </Link>
            </div>

            <p v-else class="border-silver px-5 py-10 text-2xl md:border-l">
                Nothing here just yet. Check back soon.
            </p>

            <Advert />
            <Subscribe />
        </div>
    </DefaultLayout>
</template>
