<script setup lang="ts">
import { formatPence } from '@/lib/money';
import type { ShopOrder } from '@/types/shop';

defineProps<{ order: ShopOrder }>();
</script>

<template>
    <div class="space-y-4">
        <ul class="divide-y divide-silver border-y border-silver">
            <li
                v-for="item in order.items"
                :key="item.id"
                class="flex justify-between gap-4 py-3"
            >
                <span>
                    {{ item.product }}
                    <span v-if="item.variant !== 'Standard'" class="text-sm"
                        >({{ item.variant }})</span
                    >
                    <span v-if="item.quantity > 1"> × {{ item.quantity }}</span>
                </span>
                <span>{{ formatPence(item.subtotal) }}</span>
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
