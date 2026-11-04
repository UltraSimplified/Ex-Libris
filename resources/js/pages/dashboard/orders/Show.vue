<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import OrderController from '@/actions/App/Http/Controllers/Dashboard/OrderController';
import OrderSummary from '@/components/GOP/OrderSummary.vue';
import VenueMap from '@/components/GOP/VenueMap.vue';
import { formatPence } from '@/lib/money';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import type { ShopOrder } from '@/types/shop';
import Heading from '../../../components/Heading.vue';
import AppLayout from '../../../layouts/AppLayout.vue';

const props = defineProps<{
    order: ShopOrder & {
        billing_address: string[];
        receipt_url: string | null;
        refunded_amount: number;
        posted_at: string | null;
        ready_at: string | null;
        collected_at: string | null;
        cancelled_at: string | null;
    };
    /** Where to collect from, once the office's address has been placed. */
    collection: { name: string; latitude: number; longitude: number } | null;
    /** A picture of where a posted order is going, if it could be drawn. */
    deliveryMap: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Purchases', href: OrderController.index().url },
    {
        title: props.order.reference,
        href: OrderController.show(props.order.reference).url,
    },
];

const on = (when: string | null): string =>
    when
        ? new Date(when).toLocaleDateString('en-GB', {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : '';

/** What has happened to the order, oldest first. */
const history = [
    { label: 'Paid', when: props.order.paid_at },
    { label: 'Posted', when: props.order.posted_at },
    { label: 'Ready to collect', when: props.order.ready_at },
    { label: 'Collected', when: props.order.collected_at },
    { label: 'Cancelled', when: props.order.cancelled_at },
].filter((step) => step.when);
</script>

<template>
    <Head :title="`Order ${order.reference}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-3xl flex-1 space-y-8 p-4">
            <Heading
                :title="`Order ${order.reference}`"
                :description="order.status_label"
            />

            <OrderSummary :order="order" />

            <p v-if="order.refunded_amount > 0" class="text-sm">
                {{ formatPence(order.refunded_amount) }} has been refunded to
                your card.
            </p>

            <div class="grid gap-6 sm:grid-cols-2">
                <div v-if="order.fulfilment === 'post'">
                    <Heading variant="small" title="Posting to" />
                    <p class="text-sm">
                        <template
                            v-for="(line, index) in order.shipping_address"
                            :key="index"
                            >{{ line }}<br
                        /></template>
                    </p>
                    <p v-if="order.tracking_number" class="mt-2 text-sm">
                        Tracking number:
                        <strong>{{ order.tracking_number }}</strong>
                    </p>
                    <!--
                        A picture rather than a map that can be panned: it
                        is their own address, so all it has to do is show
                        the street back to them.
                    -->
                    <figure v-if="deliveryMap" class="mt-3">
                        <img
                            :src="deliveryMap"
                            alt="A map of the delivery address"
                            class="block h-auto w-full rounded border border-silver"
                            loading="lazy"
                        />
                        <figcaption class="mt-1 text-xs">
                            &copy;
                            <a
                                href="https://www.openstreetmap.org/copyright"
                                target="_blank"
                                rel="noopener"
                                class="underline"
                                >OpenStreetMap</a
                            >
                            contributors
                        </figcaption>
                    </figure>
                </div>
                <div v-else>
                    <Heading variant="small" title="Collecting" />
                    <p class="text-sm">
                        From Good on Paper. We'll email you when it's ready.
                    </p>
                    <div v-if="collection" class="mt-3">
                        <VenueMap
                            :latitude="collection.latitude"
                            :longitude="collection.longitude"
                            :name="collection.name"
                        />
                    </div>
                </div>

                <div v-if="order.billing_address.length">
                    <Heading variant="small" title="Billing address" />
                    <p class="text-sm">
                        <template
                            v-for="(line, index) in order.billing_address"
                            :key="index"
                            >{{ line }}<br
                        /></template>
                    </p>
                </div>
            </div>

            <div>
                <Heading variant="small" title="History" />
                <ul class="text-sm">
                    <li v-for="step in history" :key="step.label">
                        {{ step.label }}: {{ on(step.when) }}
                    </li>
                </ul>
            </div>

            <p v-if="order.receipt_url">
                <a
                    :href="order.receipt_url"
                    target="_blank"
                    rel="noopener"
                    class="text-sm underline"
                    >View your receipt</a
                >
            </p>
        </div>
    </AppLayout>
</template>
