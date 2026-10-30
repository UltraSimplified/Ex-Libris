<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import OrderSummary from '@/components/GOP/OrderSummary.vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import type { ShopOrder } from '@/types/shop';

defineProps<{
    title: string;
    order: ShopOrder;
}>();
</script>

<template>
    <Head :title="title" />
    <DefaultLayout>
        <div class="md:w-full md:pl-48">
            <div class="border-b border-silver px-5 py-5 pt-4 pb-4 md:border-l">
                <h1 class="text-4xl font-semibold">
                    Order {{ order.reference }}
                </h1>
            </div>

            <div class="space-y-6 border-silver px-5 py-8 md:border-l">
                <div class="max-w-2xl space-y-6">
                    <p class="text-2xl font-bold">{{ order.status_label }}</p>

                    <p v-if="order.tracking_number">
                        Tracking number:
                        <strong>{{ order.tracking_number }}</strong>
                    </p>

                    <OrderSummary :order="order" />

                    <div v-if="order.shipping_address.length">
                        <h2 class="font-bold">Posting to</h2>
                        <p>
                            <template
                                v-for="(line, index) in order.shipping_address"
                                :key="index"
                                >{{ line }}<br
                            /></template>
                        </p>
                    </div>

                    <p class="text-sm">
                        Questions about your order? Reply to any email we've
                        sent you about it.
                    </p>
                </div>
            </div>
        </div>
    </DefaultLayout>
</template>
