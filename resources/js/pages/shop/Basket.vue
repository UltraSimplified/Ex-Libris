<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    destroy,
    fulfilment as chooseFulfilment,
    update,
} from '@/actions/App/Http/Controllers/BasketController';
import { store as startCheckout } from '@/actions/App/Http/Controllers/CheckoutController';
import { product as productRoute } from '@/actions/App/Http/Controllers/ShopController';
import ShopPrice from '@/components/GOP/ShopPrice.vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import { formatPence } from '@/lib/money';
import { Shop } from '@/routes';

/** One line of the basket. Amounts are in pence. */
interface Line {
    variant_id: number;
    product: string;
    product_slug: string;
    category_slug: string;
    variant: string;
    image: string | null;
    unit_price: number;
    /** Its usual price, struck through, when it is on sale. */
    struck_price: number | null;
    quantity: number;
    subtotal: number;
    /** False when it has sold out, or fewer are left than are asked for. */
    available: boolean;
    max_quantity: number;
}

type Fulfilment = 'post' | 'collection';

const props = defineProps<{
    title: string;
    lines: Line[];
    fulfilment: Fulfilment;
    fulfilmentOptions: { value: Fulfilment; label: string }[];
    subtotal: number;
    postage: { amount: number; uncapped: number; capped: boolean };
    total: number;
    postageCap: number;
    collection: { name: string; address: string[]; hours: string | null };
}>();

const page = usePage();

const productUrl = (line: Line): string =>
    productRoute.url({
        shopCategory: line.category_slug,
        product: line.product_slug,
    });

const quantities = (line: Line): number[] =>
    Array.from(
        { length: Math.max(line.max_quantity, line.quantity) },
        (_, index) => index + 1,
    );

const visitOptions = { preserveScroll: true };

function changeQuantity(line: Line, event: Event): void {
    const quantity = Number((event.target as HTMLSelectElement).value);

    router.patch(update.url(line.variant_id), { quantity }, visitOptions);
}

function removeLine(line: Line): void {
    router.delete(destroy.url(line.variant_id), visitOptions);
}

function choose(fulfilment: Fulfilment): void {
    if (fulfilment !== props.fulfilment) {
        router.put(chooseFulfilment.url(), { fulfilment }, visitOptions);
    }
}

const somethingUnavailable = (): boolean =>
    props.lines.some((line) => !line.available);

const checkingOut = ref(false);

/*
 * The server makes the order and answers with Stripe's address, which
 * Inertia follows as a full page visit.
 */
function checkout(): void {
    router.post(
        startCheckout.url(),
        {},
        {
            preserveScroll: true,
            onStart: () => (checkingOut.value = true),
            onFinish: () => (checkingOut.value = false),
        },
    );
}
</script>

<template>
    <Head :title="title" />
    <DefaultLayout>
        <div class="md:w-full md:pl-48">
            <div class="border-b border-silver px-5 py-5 pt-4 pb-4 md:border-l">
                <h1 class="text-4xl font-semibold">{{ title }}</h1>
            </div>

            <div
                v-if="!lines.length"
                class="border-silver px-5 py-10 md:border-l"
            >
                <p class="text-2xl">Your basket is empty.</p>
                <Link :href="Shop.url()" class="mt-4 inline-block underline"
                    >Back to the shop</Link
                >
            </div>

            <div
                v-else
                class="grid gap-10 border-silver px-5 py-8 md:border-l lg:grid-cols-[2fr_1fr]"
            >
                <ul class="divide-y divide-silver border-y border-silver">
                    <li
                        v-for="line in lines"
                        :key="line.variant_id"
                        class="flex gap-4 py-4"
                    >
                        <Link
                            :href="productUrl(line)"
                            class="h-24 w-24 shrink-0 overflow-hidden rounded bg-silver/30"
                        >
                            <img
                                v-if="line.image"
                                :src="`${page.props.cdnUrl}/${line.image}`"
                                :alt="line.product"
                                class="h-full w-full object-contain"
                            />
                        </Link>
                        <div class="flex flex-1 flex-col gap-1">
                            <Link
                                :href="productUrl(line)"
                                class="text-xl font-bold tracking-tight"
                                >{{ line.product }}</Link
                            >
                            <span v-if="line.variant !== 'Standard'">{{
                                line.variant
                            }}</span>
                            <span class="text-sm">
                                <ShopPrice
                                    :price="line.unit_price"
                                    :struck-price="line.struck_price"
                                />
                                each
                            </span>
                            <span
                                v-if="!line.available"
                                class="text-sm font-bold text-red-700"
                                >Sold out, or not enough left. Remove it or take
                                fewer to check out.</span
                            >
                            <div class="mt-2 flex items-center gap-4">
                                <label class="flex items-center gap-2 text-sm">
                                    Quantity
                                    <select
                                        :value="line.quantity"
                                        class="rounded border border-silver bg-transparent px-2 py-1"
                                        @change="changeQuantity(line, $event)"
                                    >
                                        <option
                                            v-for="quantity in quantities(line)"
                                            :key="quantity"
                                            :value="quantity"
                                        >
                                            {{ quantity }}
                                        </option>
                                    </select>
                                </label>
                                <button
                                    type="button"
                                    class="text-sm underline"
                                    @click="removeLine(line)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                        <span class="text-lg font-semibold">{{
                            formatPence(line.subtotal)
                        }}</span>
                    </li>
                </ul>

                <aside class="space-y-6">
                    <fieldset>
                        <legend class="text-xl font-bold">
                            How would you like it?
                        </legend>
                        <div class="mt-3 space-y-2">
                            <label
                                v-for="option in fulfilmentOptions"
                                :key="option.value"
                                class="flex cursor-pointer items-start gap-3 rounded border-2 p-3"
                                :class="
                                    fulfilment === option.value
                                        ? 'border-tarmac'
                                        : 'border-silver'
                                "
                            >
                                <input
                                    type="radio"
                                    name="fulfilment"
                                    :value="option.value"
                                    :checked="fulfilment === option.value"
                                    class="mt-1"
                                    @change="choose(option.value)"
                                />
                                <span>
                                    <span class="font-semibold">{{
                                        option.label
                                    }}</span>
                                    <span
                                        v-if="option.value === 'post'"
                                        class="block text-sm"
                                        >UK only. Postage per item, never more
                                        than
                                        {{ formatPence(postageCap) }}.</span
                                    >
                                    <span v-else class="block text-sm"
                                        >Free, from {{ collection.name }},
                                        {{ collection.address.join(', ') }}.
                                        We'll email you when it's ready.</span
                                    >
                                </span>
                            </label>
                        </div>
                        <p
                            v-if="
                                fulfilment === 'collection' && collection.hours
                            "
                            class="mt-3 text-sm whitespace-pre-line"
                        >
                            <span class="font-semibold">Collection hours</span
                            ><br />{{ collection.hours }}
                        </p>
                    </fieldset>

                    <dl class="space-y-2 border-t border-silver pt-4">
                        <div class="flex justify-between">
                            <dt>Subtotal</dt>
                            <dd>{{ formatPence(subtotal) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>
                                {{
                                    fulfilment === 'collection'
                                        ? 'Collection'
                                        : 'UK postage'
                                }}
                            </dt>
                            <dd>
                                {{
                                    postage.amount === 0
                                        ? 'Free'
                                        : formatPence(postage.amount)
                                }}
                            </dd>
                        </div>
                        <p v-if="postage.capped" class="text-sm">
                            Postage capped at
                            {{ formatPence(postageCap) }}, saving you
                            {{
                                formatPence(postage.uncapped - postage.amount)
                            }}.
                        </p>
                        <div
                            class="flex justify-between border-t border-silver pt-2 text-xl font-bold"
                        >
                            <dt>Total</dt>
                            <dd>{{ formatPence(total) }}</dd>
                        </div>
                    </dl>

                    <p
                        v-if="page.props.errors?.basket"
                        class="text-sm font-bold text-red-700"
                    >
                        {{ page.props.errors.basket }}
                    </p>
                    <button
                        type="button"
                        class="w-full rounded bg-tarmac px-5 py-3 text-lg font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="somethingUnavailable() || checkingOut"
                        @click="checkout"
                    >
                        {{
                            checkingOut ? 'Taking you to payment…' : 'Checkout'
                        }}
                    </button>
                    <p class="text-center text-xs">
                        You'll pay securely with Stripe.
                    </p>
                </aside>
            </div>
        </div>
    </DefaultLayout>
</template>
