<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { store as addToBasket } from '@/actions/App/Http/Controllers/BasketController';
import Advert from '@/components/GOP/Advert.vue';
import ShopPrice from '@/components/GOP/ShopPrice.vue';
import Subscribe from '@/components/GOP/Subscribe.vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import { formatPence, formatPriceRange } from '@/lib/money';

/** One colour and size of the product. Price is in pence. */
interface Variant {
    id: number;
    colour: string | null;
    colour_hex: string | null;
    size: string | null;
    name: string;
    price: number;
    /** Its usual price, struck through, when it is on sale. */
    struck_price: number | null;
    in_stock: boolean;
}

/** A picture after the main one, tied to a colour or shown for them all. */
interface ProductImage {
    id: number;
    path: string;
    colour: string | null;
    alt_text: string | null;
}

interface Props {
    title: string;
    category: { name: string; slug: string };
    product: {
        name: string;
        slug: string;
        summary: string | null;
        description: string | null;
        image: string | null;
        images: ProductImage[];
        variants: Variant[];
    };
    /** Postage per item in pence, the most an order pays, and where to collect free. */
    delivery: { band: string; amount: number; cap: number; collection: string };
}

const props = defineProps<Props>();
const page = usePage();

const imageUrl = (path: string): string => `${page.props.cdnUrl}/${path}`;

/*
 * The colours and sizes on offer, each once, in the order the office set
 * the variants - which puts sizes smallest first.
 */
const colours = computed(() => {
    const seen = new Map<string, { name: string; hex: string | null }>();

    props.product.variants.forEach((variant) => {
        if (variant.colour && !seen.has(variant.colour)) {
            seen.set(variant.colour, {
                name: variant.colour,
                hex: variant.colour_hex,
            });
        }
    });

    return [...seen.values()];
});

const sizes = computed(() => [
    ...new Set(
        props.product.variants
            .map((variant) => variant.size)
            .filter((size): size is string => size !== null),
    ),
]);

const variantFor = (
    colour: string | null,
    size: string | null,
): Variant | undefined =>
    props.product.variants.find(
        (variant) => variant.colour === colour && variant.size === size,
    );

const colourInStock = (colour: string): boolean =>
    props.product.variants.some(
        (variant) => variant.colour === colour && variant.in_stock,
    );

/*
 * Start on the first colour with anything left, and on the size if there
 * is only one to choose.
 */
const selectedColour = ref<string | null>(
    colours.value.find((colour) => colourInStock(colour.name))?.name ??
        colours.value[0]?.name ??
        null,
);
const selectedSize = ref<string | null>(
    sizes.value.length === 1 ? sizes.value[0] : null,
);

const sizeAvailable = (size: string): boolean =>
    variantFor(selectedColour.value, size)?.in_stock ?? false;

/*
 * A size chosen in one colour may not exist, or be sold out, in the next;
 * rather than quietly pointing at nothing, the choice is cleared.
 */
watch(selectedColour, () => {
    if (selectedSize.value && !sizeAvailable(selectedSize.value)) {
        selectedSize.value = sizes.value.length === 1 ? sizes.value[0] : null;
    }
});

/** The variant the choices add up to, once there is enough to choose it. */
const selectedVariant = computed<Variant | undefined>(() => {
    if (props.product.variants.length === 1) {
        return props.product.variants[0];
    }

    if (sizes.value.length && !selectedSize.value) {
        return undefined;
    }

    return variantFor(selectedColour.value, selectedSize.value);
});

/*
 * The variant whose price to show: the one chosen, or the only price there
 * is when every colour and size costs the same. Otherwise the span of
 * prices is shown until a choice is made.
 */
const pricedVariant = computed<Variant | undefined>(() => {
    if (selectedVariant.value) {
        return selectedVariant.value;
    }

    const prices = new Set(
        props.product.variants.map((variant) => variant.price),
    );

    return prices.size === 1 ? props.product.variants[0] : undefined;
});

const priceRange = computed(() => {
    const prices = props.product.variants.map((variant) => variant.price);

    return formatPriceRange(Math.min(...prices), Math.max(...prices));
});

const soldOut = computed(() =>
    props.product.variants.every((variant) => !variant.in_stock),
);

/*
 * The main picture, then those for the chosen colour and those for every
 * colour. Changing colour goes back to the first picture.
 */
const gallery = computed(() => {
    const pictures: {
        key: string;
        path: string;
        alt: string;
        colour: string | null;
    }[] = [];

    if (props.product.image) {
        pictures.push({
            key: 'main',
            path: props.product.image,
            alt: props.product.name,
            colour: null,
        });
    }

    props.product.images
        .filter(
            (image) =>
                image.colour === null || image.colour === selectedColour.value,
        )
        .forEach((image) =>
            pictures.push({
                key: `image-${image.id}`,
                path: image.path,
                alt: image.alt_text || props.product.name,
                colour: image.colour,
            }),
        );

    return pictures;
});

const shownPicture = ref(0);

const basketForm = useForm<{ variant_id: number | null; quantity: number }>({
    variant_id: null,
    quantity: 1,
});

function submitToBasket(): void {
    if (!selectedVariant.value) {
        return;
    }

    basketForm.variant_id = selectedVariant.value.id;
    basketForm.post(addToBasket.url());
}

watch(selectedColour, (colour) => {
    shownPicture.value = Math.max(
        0,
        gallery.value.findIndex((picture) => picture.colour === colour),
    );
});
</script>

<template>
    <Head :title="title" />
    <DefaultLayout>
        <div class="md:w-full md:pl-48">
            <div class="border-b border-silver px-5 py-5 pt-4 pb-4 md:border-l">
                <h1 class="text-4xl font-semibold">{{ product.name }}</h1>
            </div>

            <div
                class="grid gap-10 border-silver px-5 py-8 md:border-l lg:grid-cols-[minmax(0,auto)_minmax(20rem,1fr)]"
            >
                <div>
                    <div class="relative">
                        <img
                            v-if="gallery[shownPicture]"
                            :src="imageUrl(gallery[shownPicture].path)"
                            :alt="gallery[shownPicture].alt"
                            class="block h-auto max-w-full"
                        />
                    </div>
                    <div
                        v-if="gallery.length > 1"
                        class="mt-3 grid grid-cols-5 gap-2"
                    >
                        <button
                            v-for="(picture, index) in gallery"
                            :key="picture.key"
                            type="button"
                            class="aspect-square overflow-hidden rounded border-2"
                            :class="
                                index === shownPicture
                                    ? 'border-tarmac'
                                    : 'border-transparent'
                            "
                            :aria-label="`Show picture ${index + 1}`"
                            @click="shownPicture = index"
                        >
                            <img
                                :src="imageUrl(picture.path)"
                                :alt="picture.alt"
                                class="h-full w-full object-contain"
                            />
                        </button>
                    </div>
                </div>

                <div class="max-w-[666px]">
                    <p v-if="product.summary" class="text-xl">
                        {{ product.summary }}
                    </p>

                    <p class="mt-4 text-3xl font-bold">
                        <ShopPrice
                            v-if="pricedVariant"
                            :price="pricedVariant.price"
                            :struck-price="pricedVariant.struck_price"
                        />
                        <template v-else>{{ priceRange }}</template>
                    </p>

                    <fieldset v-if="colours.length" class="mt-6">
                        <legend class="font-semibold">
                            Colour<span v-if="selectedColour"
                                >: {{ selectedColour }}</span
                            >
                        </legend>
                        <div class="mt-2 flex flex-wrap gap-3">
                            <button
                                v-for="colour in colours"
                                :key="colour.name"
                                type="button"
                                class="h-10 w-10 rounded-full border-2 ring-offset-2"
                                :class="[
                                    selectedColour === colour.name
                                        ? 'ring-2 ring-tarmac'
                                        : '',
                                    colourInStock(colour.name)
                                        ? 'border-silver'
                                        : 'border-silver opacity-40',
                                ]"
                                :style="{
                                    backgroundColor: colour.hex ?? undefined,
                                }"
                                :aria-label="colour.name"
                                :aria-pressed="selectedColour === colour.name"
                                :title="colour.name"
                                @click="selectedColour = colour.name"
                            >
                                <span v-if="!colour.hex" class="text-xs">{{
                                    colour.name.slice(0, 2)
                                }}</span>
                            </button>
                        </div>
                    </fieldset>

                    <fieldset v-if="sizes.length" class="mt-6">
                        <legend class="font-semibold">Size</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="size in sizes"
                                :key="size"
                                type="button"
                                class="min-w-12 rounded border-2 px-3 py-2 font-semibold"
                                :class="[
                                    selectedSize === size
                                        ? 'border-tarmac bg-tarmac text-white'
                                        : 'border-silver',
                                    sizeAvailable(size)
                                        ? ''
                                        : 'cursor-not-allowed line-through opacity-40',
                                ]"
                                :disabled="!sizeAvailable(size)"
                                :aria-pressed="selectedSize === size"
                                @click="selectedSize = size"
                            >
                                {{ size }}
                            </button>
                        </div>
                    </fieldset>

                    <p v-if="soldOut" class="mt-6 text-xl font-bold uppercase">
                        Sold out
                    </p>
                    <p
                        v-else-if="selectedVariant && !selectedVariant.in_stock"
                        class="mt-6 text-xl font-bold"
                    >
                        {{ selectedVariant.name }} is sold out.
                    </p>
                    <form
                        v-else-if="page.props.basket"
                        class="mt-6 flex flex-wrap items-end gap-3"
                        @submit.prevent="submitToBasket"
                    >
                        <label class="flex flex-col text-sm font-semibold">
                            Quantity
                            <select
                                v-model.number="basketForm.quantity"
                                class="mt-1 rounded border border-silver bg-transparent px-3 py-2"
                            >
                                <option v-for="n in 10" :key="n" :value="n">
                                    {{ n }}
                                </option>
                            </select>
                        </label>
                        <button
                            type="submit"
                            class="rounded bg-tarmac px-6 py-3 text-lg font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="
                                !selectedVariant || basketForm.processing
                            "
                        >
                            {{
                                selectedVariant
                                    ? 'Add to basket'
                                    : 'Choose a size'
                            }}
                        </button>
                        <p
                            v-if="basketForm.errors.variant_id"
                            class="w-full text-sm font-bold text-red-700"
                        >
                            {{ basketForm.errors.variant_id }}
                        </p>
                    </form>
                    <p v-else class="mt-6 text-lg font-semibold">
                        On sale soon.
                    </p>

                    <p class="mt-4 text-sm">
                        UK postage {{ formatPence(delivery.amount) }} each, and
                        never more than {{ formatPence(delivery.cap) }} an
                        order. Or collect it free from
                        {{ delivery.collection }}.
                    </p>

                    <div
                        v-if="product.description"
                        class="rich-text mt-8 text-lg md:text-base"
                        v-html="product.description"
                    ></div>
                </div>
            </div>

            <Advert />
            <Subscribe />
        </div>
    </DefaultLayout>
</template>
