<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import OrderController from '@/actions/App/Http/Controllers/Dashboard/OrderController';
import { useAccountCrumb } from '@/composables/useAccountName';
import { formatPence } from '@/lib/money';
import { Shop } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import Heading from '../../../components/Heading.vue';
import AppLayout from '../../../layouts/AppLayout.vue';

/** An order as its row in the list shows it. Money is in pence. */
interface OrderRow {
    reference: string;
    paid_at: string | null;
    status: string;
    status_label: string;
    fulfilment: 'post' | 'collection';
    item_count: number;
    total: number;
}

defineProps<{
    orders: {
        data: OrderRow[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    useAccountCrumb(),
    { title: 'Purchases', href: OrderController.index().url },
];

const paidOn = (order: OrderRow): string =>
    order.paid_at
        ? new Date(order.paid_at).toLocaleDateString('en-GB', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '';
</script>

<template>
    <Head title="Purchases" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-6 p-4">
            <Heading
                title="Purchases"
                description="Everything you have bought from Good on Paper, in one place."
            />

            <div class="space-y-3">
                <div
                    v-if="!orders.data.length"
                    class="rounded-xl border border-dashed border-sidebar-border/70 p-8 text-center dark:border-sidebar-border"
                >
                    <p class="text-sm text-muted-foreground">
                        Nothing here yet.
                        <Link :href="Shop.url()" class="underline"
                            >Have a look in the shop</Link
                        >.
                    </p>
                </div>

                <div
                    v-else
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <ul class="divide-y divide-sidebar-border/70">
                        <li v-for="order in orders.data" :key="order.reference">
                            <Link
                                :href="OrderController.show(order.reference)"
                                class="flex flex-wrap items-center justify-between gap-x-6 gap-y-1 p-4 hover:bg-muted/50"
                            >
                                <span class="font-semibold">{{
                                    order.reference
                                }}</span>
                                <span class="text-sm text-muted-foreground">
                                    {{ paidOn(order) }} ·
                                    {{ order.item_count }}
                                    {{
                                        order.item_count === 1
                                            ? 'item'
                                            : 'items'
                                    }}
                                    ·
                                    {{
                                        order.fulfilment === 'collection'
                                            ? 'Collection'
                                            : 'Posted'
                                    }}
                                </span>
                                <span class="text-sm">{{
                                    order.status_label
                                }}</span>
                                <span class="font-semibold">{{
                                    formatPence(order.total)
                                }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>

                <nav
                    v-if="orders.prev_page_url || orders.next_page_url"
                    class="flex justify-between text-sm"
                >
                    <Link
                        v-if="orders.prev_page_url"
                        :href="orders.prev_page_url"
                        class="underline"
                        >Newer purchases</Link
                    >
                    <span v-else></span>
                    <Link
                        v-if="orders.next_page_url"
                        :href="orders.next_page_url"
                        class="underline"
                        >Older purchases</Link
                    >
                </nav>
            </div>
        </div>
    </AppLayout>
</template>
