<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import OrderSummary from '@/components/GOP/OrderSummary.vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import { Shop } from '@/routes';
import type { ShopOrder } from '@/types/shop';

const props = defineProps<{
    title: string;
    order: ShopOrder;
    /** The signed link to the order, as their email gives it. */
    orderUrl: string;
    /** For a guest's order, the link that adds it to an account. */
    claimUrl: string | null;
}>();

const paid = computed(() => props.order.status !== 'pending');

/*
 * Stripe tells the shop the order is paid a moment after the customer
 * lands here, so the page asks again every couple of seconds - for twenty
 * seconds at most, after which the email will tell them.
 */
const asked = ref(0);
const { stop } = usePoll(2000, {
    only: ['order'],
    onFinish: () => {
        asked.value++;
    },
});

watch(
    [paid, asked],
    () => {
        if (paid.value || asked.value >= 10) {
            stop();
        }
    },
    { immediate: true },
);
</script>

<template>
    <Head :title="title" />
    <DefaultLayout>
        <div class="md:w-full md:pl-48">
            <div class="border-b border-silver px-5 py-5 pt-4 pb-4 md:border-l">
                <h1 class="text-4xl font-semibold">Thank you</h1>
            </div>

            <div class="space-y-6 border-silver px-5 py-8 md:border-l">
                <div class="max-w-2xl space-y-6">
                    <p class="text-2xl">
                        Your order is <strong>{{ order.reference }}</strong
                        >.
                    </p>

                    <p v-if="paid" class="text-lg">
                        It's paid for, and we've emailed a confirmation to
                        {{ order.email }}.
                    </p>
                    <p v-else-if="asked < 10" class="animate-pulse text-lg">
                        Confirming your payment…
                    </p>
                    <p v-else class="text-lg">
                        We're still waiting to hear from our payment provider.
                        You'll get an email as soon as your payment is
                        confirmed.
                    </p>

                    <OrderSummary :order="order" />

                    <p>
                        <a :href="orderUrl" class="underline"
                            >Keep this link to check on your order</a
                        >, or find it in your confirmation email.
                    </p>

                    <p v-if="claimUrl">
                        <a :href="claimUrl" class="underline"
                            >Add this order to your account</a
                        >, or create one to keep track of your orders.
                    </p>

                    <Link :href="Shop.url()" class="inline-block underline"
                        >Back to the shop</Link
                    >
                </div>
            </div>
        </div>
    </DefaultLayout>
</template>
