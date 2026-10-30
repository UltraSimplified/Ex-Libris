<script setup lang="ts">
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { usePage, router, Link, useRemember } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, nextTick, ref, watch } from 'vue';
import type { Ref } from 'vue';
import ListingAdvert from '@/components/GOP/ListingAdvert.vue';
import ListingRow from '@/components/GOP/ListingRow.vue';
import PromoterProfile from '@/components/GOP/PromoterProfile.vue';
import VenueProfile from '@/components/GOP/VenueProfile.vue';
import { useAdvertLineUp } from '@/composables/useAdvertLineUp';
import { useBookmarks } from '@/composables/useBookmarks';
import { resolvePlaceholders } from '@/composables/usePlaceholders.js';
import { useScrollReveal } from '@/composables/useScrollReveal';
import { formatListingDates } from '@/lib/listingDates';
import { register } from '@/routes';
import type { Advert, Listing, Promoter, Venue } from '@/types/global';

interface Props {
    title: string;
    listings: Listing[];
    all: boolean;
    today: boolean;
    tomorrow: boolean;
    thisWeek: boolean;
    nextWeek: boolean;
    bookmarked?: boolean;
    venue: Venue;
    promoter?: Promoter | null;
}
/**
 * The date picker, fetched the first time somebody goes to choose dates.
 *
 * It carries a whole Tailwind build of its own and writes it into a
 * <style> the moment it is imported. Coming after the site's stylesheet,
 * in the same layers, its plain utilities beat the site's responsive and
 * dark ones - a `text-sm` over `md:text-xs`, a `bg-white` over
 * `dark:bg-surface` - here and on every page visited after this one. So
 * it waits until it is wanted, and its stylesheet is moved ahead of the
 * site's: it still supplies the picker's own colours, and loses wherever
 * the two overlap. The one exception is the reset it carries for form
 * fields, which picks them out by type and so outranks the site's plain
 * `input` rule: a field that should not turn white says `bg-transparent`.
 */
const VueTailwindDatePicker = defineAsyncComponent(async () => {
    const stylesBefore = new Set(document.head.querySelectorAll('style'));
    const picker = await import('vue-tailwind-datepicker');
    const pickerStyles = [
        ...document.head.querySelectorAll<HTMLStyleElement>(
            'style:not([data-vite-dev-id])',
        ),
    ].filter((style) => !stylesBefore.has(style));

    document.head.prepend(...pickerStyles);

    return picker.default;
});

const showDatePicker = ref(false);
const dateValue = ref<{ startDate: string; endDate: string }>({
    startDate: '',
    endDate: '',
});

const formatter = { date: 'YYYY-MM-DD', month: 'MMM' };

const applyDates = () => {
    const { startDate, endDate } = dateValue.value;
    if (!startDate || !endDate) return;
    router.visit(`/listings/dates/${startDate}/${endDate}/`);
};

const page = usePage();
const { containerRef, revealAll } = useScrollReveal();
const { signedIn, count: bookmarkCount } = useBookmarks();
const props = defineProps<Props>();

/**
 * The venue or promoter whose own page this is, if it is one: it heads
 * the page in place of the sub-nav and search.
 */
const profileOwner = computed<Venue | Promoter | null>(
    () => props.venue ?? props.promoter ?? null,
);

/**
 * Whether any advert went in among the listings, so the page can leave
 * out the one it would otherwise put after them.
 */
const emit = defineEmits<{ advertsShown: [shown: boolean] }>();

/**
 * Listings with resolved image paths — CDN-prefixed where a thumbnail exists,
 * or a deterministic local placeholder where it does not.
 */
const resolvedListings = computed(() =>
    resolvePlaceholders(props.listings, page.props.cdnUrl),
);

/*
 * Kept with the page's place in history, so coming back from a listing
 * finds the search as it was left rather than the whole list again.
 */
const searchState = useRemember(
    { text: '', open: false },
    'listings-search',
) as Ref<{ text: string; open: boolean }>;

const search = computed<string>({
    get: () => searchState.value.text,
    set: (text) => (searchState.value.text = text),
});

/*
 * The search box opens from the sub-nav rather than sitting over the list
 * all the time. Opened, it takes the cursor so the reader can type at
 * once; closed, it lets go of what was typed, so the list is never left
 * filtered by words nobody can see. A venue's page lists few enough
 * events to read through, and has no sub-nav, so it has no search; nor
 * has a promoter's.
 */
const searchOpen = computed<boolean>({
    get: () => searchState.value.open,
    set: (open) => (searchState.value.open = open),
});
const searchInput = ref<HTMLInputElement | null>(null);

const toggleSearch = async () => {
    searchOpen.value = !searchOpen.value;

    if (searchOpen.value) {
        await nextTick();
        // Without scrolling to it, which would cut the ease short.
        searchInput.value?.focus({ preventScroll: true });
    } else {
        search.value = '';
    }
};

/**
 * Everything a row can be found by, in one lowercased string each.
 *
 * Built per listing rather than per keystroke: the whole list is already
 * in the page, so the only cost worth avoiding is doing this work again
 * for every letter typed.
 */
const searchable = computed(() =>
    resolvedListings.value.map((listing) => ({
        listing,
        haystack: [
            listing.title,
            listing.venue?.name,
            listing.venue?.town,
            listing.promoter?.name,
            listing.category?.name,
            listing.ticket_price,
            formatListingDates(listing),
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase(),
    })),
);

/**
 * Every word typed has to appear somewhere in the row, in any order and
 * across any of the fields, so "sub rooms jazz" finds a jazz night at
 * The Sub Rooms without anybody having to guess the word order.
 */
const visibleListings = computed(() => {
    const words = search.value
        .trim()
        .toLowerCase()
        .split(/\s+/)
        .filter(Boolean);

    if (words.length === 0) {
        return resolvedListings.value;
    }

    return searchable.value
        .filter(({ haystack }) =>
            words.every((word) => haystack.includes(word)),
        )
        .map(({ listing }) => listing);
});

const filtering = computed<boolean>(() => search.value.trim().length > 0);

/*
 * The listings as they are laid out: an advert from the live line-up after
 * every so many of them (advertEvery, from LISTINGS_ADVERT_EVERY), though
 * never as the last row. The adverts go in turn from the one after the
 * advert this page was given, so none is shown twice running and, as the
 * page's own advert rotates, the slots share out evenly.
 */
type Row =
    | { kind: 'listing'; key: string; listing: Listing }
    | { kind: 'advert'; key: string; advert: Advert };

const advertLineUp = useAdvertLineUp();

const rows = computed<Row[]>(() => {
    const every = page.props.advertEvery ?? 0;
    const lineUp = advertLineUp.value;
    const listings = visibleListings.value;
    const laidOut: Row[] = [];
    let slot = 0;

    listings.forEach((listing, index) => {
        laidOut.push({
            kind: 'listing',
            key: `listing-${listing.id}`,
            listing,
        });

        const due = every > 0 && (index + 1) % every === 0;

        if (due && lineUp.length > 0 && index + 1 < listings.length) {
            laidOut.push({
                kind: 'advert',
                key: `advert-${slot}`,
                advert: lineUp[slot % lineUp.length],
            });
            slot++;
        }
    });

    return laidOut;
});

watch(
    () => rows.value.some((row) => row.kind === 'advert'),
    (shown) => emit('advertsShown', shown),
    { immediate: true },
);

/**
 * Before the list is re-rendered, not after: the reveal's inline styles
 * have to be gone by the time the fade wants to run, or they win.
 */
watch(search, () => revealAll());
</script>
<template>
    <!-- LISTINGS -->

    <!--
        A venue's or a promoter's page is headed with its own name, and the
        usual "Listings for" heading moves down to sit over its listings,
        below its picture, description and how to find or reach it.
    -->
    <div class="border-b border-silver px-5 pt-4 pb-4 md:border-l">
        <h1 class="text-4xl font-semibold">
            {{ profileOwner ? `${profileOwner.name}.` : title }}
        </h1>
    </div>
    <template v-if="profileOwner">
        <VenueProfile v-if="props.venue" :venue="props.venue" />
        <PromoterProfile
            v-else-if="props.promoter"
            :promoter="props.promoter"
        />
        <div class="border-b border-silver px-5 pt-4 pb-4 md:border-l">
            <h2 class="text-4xl font-semibold">{{ title }}</h2>
        </div>
    </template>
    <div
        v-if="!profileOwner"
        class="border-b border-silver px-5 pt-5 pb-4 md:border-l"
    >
        <h2 class="text-l">
            <Link href="/listings/">
                <strong v-if="props.all">ALL</strong>
                <span v-else>ALL</span>
            </Link>
            &nbsp;|&nbsp;
            <Link href="/listings/today">
                <strong v-if="props.today">TODAY</strong>
                <span v-else>TODAY</span>
            </Link>
            &nbsp;|&nbsp;

            <Link href="/listings/thisweek/">
                <strong v-if="props.thisWeek">THIS WEEK</strong>
                <span v-else>THIS WEEK</span>
            </Link>
            &nbsp;|&nbsp;
            <!--Link href="/listings/nextweek/">
                <strong v-if="props.nextWeek">NEXT WEEK</strong>
                <span v-else>NEXT WEEK</span>
            </Link>
            &nbsp;|&nbsp; -->
            <span class="cursor-pointer" @click="showDatePicker = true"
                >CHOOSE DATES</span
            >
            <!-- Nothing saved, nothing to send anyone to. -->
            <template v-if="bookmarkCount > 0">
                &nbsp;|&nbsp;
                <Link href="/listings/bookmarked/" class="whitespace-nowrap">
                    <!--
                        Inline rather than flex: a flex container takes its
                        baseline from its first item, which would sit the
                        heart on the line and lift the words off it.
                    -->
                    <svg
                        class="mr-1 inline-block h-4 w-4 align-[-0.15em]"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg"
                        :fill="props.bookmarked ? 'currentColor' : 'none'"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path
                            d="M12 20.4 3.9 12.3a5 5 0 0 1 0-7.1 5 5 0 0 1 7.1 0l1 1 1-1a5 5 0 0 1 7.1 0 5 5 0 0 1 0 7.1Z"
                        />
                    </svg>
                    <strong v-if="props.bookmarked">MY BOOKMARKS</strong>
                    <span v-else>MY BOOKMARKS</span>
                </Link>
            </template>
            <template v-if="resolvedListings.length > 0">
                &nbsp;|&nbsp;
                <button
                    type="button"
                    class="cursor-pointer whitespace-nowrap uppercase"
                    aria-controls="listing-search-panel"
                    :aria-expanded="searchOpen"
                    @click="toggleSearch"
                >
                    <MagnifyingGlassIcon
                        class="mr-1 inline-block h-4 w-4 stroke-[2.5] align-[-0.15em]"
                        aria-hidden="true"
                    />
                    <strong v-if="searchOpen">SEARCH</strong>
                    <span v-else>SEARCH</span>
                </button>
            </template>
        </h2>
    </div>
    <!--
        Without an account, bookmarks live in this browser alone. An account
        keeps them on every device, and can send reminders about them.
    -->
    <div
        v-if="props.bookmarked && !signedIn"
        class="border-b border-silver px-5 pt-4 pb-4 font-din text-sm uppercase md:border-l md:text-xs"
    >
        Saved in this browser.
        <Link :href="register()" class="underline">Create an account</Link>
        to keep your bookmarks on every device and get reminders.
    </div>
    <!--
        Opened and closed by easing its height, as the header eases in and
        out: a grid row between none and all of its content, so the panel
        need not know how tall it is. Closed, it is inert, so neither the
        keyboard nor a screen reader can reach the box out of sight.
    -->
    <div
        v-if="resolvedListings.length > 0 && !profileOwner"
        id="listing-search-panel"
        class="grid"
        :class="
            searchOpen
                ? 'grid-rows-[1fr] motion-safe:transition-[grid-template-rows] motion-safe:duration-500 motion-safe:ease-[cubic-bezier(0.22,1,0.36,1)]'
                : 'grid-rows-[0fr] motion-safe:transition-[grid-template-rows] motion-safe:duration-300 motion-safe:ease-[cubic-bezier(0.4,0,1,1)]'
        "
        :inert="!searchOpen"
    >
        <div class="min-h-0 overflow-hidden">
            <div
                class="border-b border-silver bg-[#f7f7f7] px-5 pt-5 pb-5 md:border-l dark:bg-white/5"
            >
                <label for="listing-search" class="sr-only"
                    >Search these listings</label
                >
                <input
                    id="listing-search"
                    ref="searchInput"
                    v-model="search"
                    type="search"
                    placeholder="Search by venue, promoter, genre or title"
                    class="w-full border border-silver bg-surface px-4 py-3 font-din text-xl placeholder:text-tyre focus:border-tarmac focus:outline-none md:max-w-2xl md:text-lg"
                />
                <p
                    v-if="filtering"
                    aria-live="polite"
                    class="mt-2 font-din text-sm text-tyre uppercase md:text-xs"
                >
                    {{ visibleListings.length }}
                    {{ visibleListings.length === 1 ? 'event' : 'events' }}
                </p>
            </div>
        </div>
    </div>
    <div
        v-if="showDatePicker"
        class="fixed inset-0 z-60 flex flex-col bg-surface"
    >
        <div class="flex h-full w-full flex-col md:mx-auto md:max-w-3xl">
            <div
                class="flex items-center justify-between border-b border-silver p-5"
            >
                <h2 class="text-2xl font-bold">Choose your dates</h2>
            </div>

            <div class="flex-1 overflow-y-auto p-5">
                <VueTailwindDatePicker
                    v-model="dateValue"
                    :formatter="formatter"
                    :shortcuts="false"
                    no-input
                    use-range
                />
            </div>

            <div
                class="flex items-center justify-between border-t border-silver p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))]"
            >
                <button
                    class="font-semibold underline"
                    @click="showDatePicker = false"
                >
                    Cancel
                </button>
                <button
                    class="rounded bg-tarmac px-7 py-3 font-din font-semibold text-white uppercase hover:bg-hivis hover:text-tarmac dark:bg-hivis dark:text-tarmac dark:hover:bg-black dark:hover:text-hivis"
                    @click="applyDates"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
    <div v-if="visibleListings.length > 0">
        <div
            ref="containerRef"
            class="grid grid-cols-1 divide-y divide-silver border-silver md:border-l"
        >
            <!--
                Rendered without a tag of its own, so the rows stay direct
                children of the grid: the reveal observer looks for those,
                and a wrapper between them would hide every one. A ref on
                the group itself would hand the observer a component rather
                than an element, so it goes on the grid.
            -->
            <TransitionGroup name="listing">
                <div v-for="row in rows" :key="row.key">
                    <ListingAdvert
                        v-if="row.kind === 'advert'"
                        :advert="row.advert"
                    />
                    <ListingRow v-else :listing="row.listing" />
                </div>
            </TransitionGroup>
        </div>
    </div>
    <div v-else class="border-silver px-5 py-5 md:border-l">
        <h1 v-if="filtering" class="font-bold uppercase">
            Nothing matches &ldquo;{{ search.trim() }}&rdquo;
        </h1>
        <h1 v-else-if="props.bookmarked" class="font-bold uppercase">
            No bookmarked events
        </h1>
        <h1 v-else class="font-bold uppercase">No events listed</h1>
    </div>
</template>

<style scoped>
/*
 * Rows fade rather than blink out. A leaving row keeps its place until the
 * fade finishes and then collapses, so the dividers between rows never
 * outlive what they were dividing.
 */
.listing-enter-active,
.listing-leave-active {
    transition: opacity 0.25s ease;
}

.listing-enter-from,
.listing-leave-to {
    opacity: 0;
}
</style>
