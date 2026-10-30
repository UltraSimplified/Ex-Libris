<script setup lang="ts">
import { formatPence } from '@/lib/money';

/**
 * What something costs, and when it is on sale, its usual price struck
 * through after it. Amounts are in pence.
 */
defineProps<{
    price: number;
    struckPrice?: number | null;
}>();
</script>

<template>
    <span>
        <span>{{ formatPence(price) }}</span>
        <template v-if="struckPrice">
            {{ ' ' }}
            <s class="font-normal opacity-60">
                <span class="sr-only">usually </span
                >{{ formatPence(struckPrice) }}
            </s>
        </template>
    </span>
</template>
