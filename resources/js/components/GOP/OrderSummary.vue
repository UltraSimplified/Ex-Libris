<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { formatPence } from '@/lib/money';
import type { ShopOrder } from '@/types/shop';

defineProps<{ order: ShopOrder }>();

const page = usePage();
</script>

<template>
    <div class="space-y-4">
        <ul class="divide-y divide-silver border-y border-silver">
            <li
                v-for="item in order.items"
                :key="item.id"
                class="flex items-start justify-between gap-4 py-3"
            >
                <span class="flex min-w-0 items-start gap-3">
                    <!--
                        The picture the line was bought with, kept small
                        enough that a phone shows name and price on one
                        line beside it. Lines ordered before pictures were
                        kept have none, and leave the space empty rather
                        than holding a placeholder.
                    -->
                    <img
                        v-if="item.image"
                        :src="`${page.props.cdnUrl}/${item.image}`"
                        :alt="item.product"
                        class="size-14 shrink-0 border border-silver object-contain"
                        loading="lazy"
                    />
                    <span class="min-w-0">
                        {{ item.product }}
                        <span v-if="item.variant !== 'Standard'" class="text-sm"
                            >({{ item.variant }})</span
                        >
                        <span v-if="item.quantity > 1">
                            × {{ item.quantity }}</span
                        >
                    </span>
                </span>
                <span class="shrink-0">{{ formatPence(item.subtotal) }}</span>
            </li>
        </ul>
        <dl class="space-y-1">
            <div class="flex justify-between">
                <dt>Subtotal</dt>
                <dd>{{ formatPence(order.subtotal) }}</dd>
            </div>
            <div class="flex justify-between">
                <dt>
                    {{
                        order.fulfilment === 'collection'
                            ? 'Collection'
                            : 'UK postage'
                    }}
                </dt>
                <dd>
                    {{
                        order.shipping === 0
                            ? 'Free'
                            : formatPence(order.shipping)
                    }}
                </dd>
            </div>
            <div class="flex justify-between text-lg font-bold">
                <dt>Total</dt>
                <dd>{{ formatPence(order.total) }}</dd>
            </div>
        </dl>
    </div>
</template>
