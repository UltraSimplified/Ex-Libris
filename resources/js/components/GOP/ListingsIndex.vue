<script setup lang="ts">
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { usePage, router, Link, useRemember } from '@inertiajs/vue3';
import {
    computed,
    defineAsyncComponent,
    nextTick,
    onMounted,
    ref,
    watch,
} from 'vue';
import type { Ref } from 'vue';
import ListingAdvert from '@/components/GOP/ListingAdvert.vue';
import ListingRow from '@/components/GOP/ListingRow.vue';
import PromoterProfile from '@/components/GOP/PromoterProfile.vue';
import VenueProfile from '@/components/GOP/VenueProfile.vue';
import { useAdvertLineUp } from '@/composables/useAdvertLineUp';
import { useBookmarks } from '@/composables/useBookmarks';
import { provideGenreTags } from '@/composables/useGenreTags';
import type { GenreTags } from '@/composables/useGenreTags';
import { resolvePlaceholders } from '@/composables/usePlaceholders.js';
import { useScrollReveal } from '@/composables/useScrollReveal';
import { collapseOnLeave } from '@/lib/collapseOnLeave';
import { formatListingDates, listingHeading } from '@/lib/listingDates';
import type { ListingHeading } from '@/lib/listingDates';
import { register } from '@/routes';
import type {
    Advert,
    Category,
    LineUpArtist,
    Listing,
    Promoter,
    Venue,
} from '@/types/global';

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
    genres?: Category[];
    /** The server's today, as "2026-11-12". */
    currentDay: string;
    /** The day this page's order counts from. */
    firstDay: string;
    /** Whether to head the list by day, which a page about one day is not. */
    dayHeadings: boolean;
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
    router.visit(withGenre(`/listings/dates/${startDate}/${endDate}/`));
};

const page = usePage();
const { containerRef, revealAll } = useScrollReveal();
const { signedIn, count: bookmarkCount, isBookmarked } = useBookmarks();
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
 * or a deterministic local placeholder where it does not. My Bookmarks goes by
 * what is saved now, so a row leaves the moment its heart is emptied.
 */
const resolvedListings = computed(() =>
    resolvePlaceholders(
        props.bookmarked
            ? props.listings.filter(({ id }) => isBookmarked(id))
            : props.listings,
        page.props.cdnUrl,
    ),
);

/**
 * The genres the listings are narrowed to, if any, held in the address as
 * ?genre=film,quiz so they go along with the reader from Today to This
 * Week, come back with them from a listing, and can be shared. Only genres
 * there is a pill for count, so a mistyped one narrows nothing.
 */
const chosenGenres = computed<Category[]>(() => {
    const slugs = (
        new URL(page.url, 'https://listings.invalid').searchParams.get(
            'genre',
        ) ?? ''
    ).split(',');

    return (props.genres ?? []).filter(({ slug }) => slugs.includes(slug));
});

const chosenSlugs = computed<string[]>(() =>
    chosenGenres.value.map(({ slug }) => slug),
);

const isChosen = (slug: string): boolean => chosenSlugs.value.includes(slug);

/**
 * The genres as the address carries them, in the order of the row, so the
 * same choice always makes the same address. Slugs need no escaping, and
 * the commas between them are left as commas to be read.
 */
const genreQuery = (slugs: string[]): string =>
    slugs.length > 0
        ? `?genre=${(props.genres ?? [])
              .map(({ slug }) => slug)
              .filter((slug) => slugs.includes(slug))
              .join(',')}`
        : '';

/**
 * A link to another set of dates that keeps the genres chosen.
 */
const withGenre = (path: string): string =>
    path + genreQuery(chosenSlugs.value);

/**
 * Choosing genres changes only the address: every listing for these dates
 * is already in the page, so there is nothing to fetch.
 */
const chooseGenres = (slugs: string[], onChosen?: () => void): void => {
    const path = new URL(page.url, 'https://listings.invalid').pathname;

    router.replace({
        url: path + genreQuery(slugs),
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => onChosen?.(),
    });
};

/**
 * A pill adds its genre to those chosen, or takes it away again.
 */
const toggleGenre = (slug: string): void =>
    chooseGenres(
        isChosen(slug)
            ? chosenSlugs.value.filter((chosen) => chosen !== slug)
            : [...chosenSlugs.value, slug],
    );

/**
 * On a phone the genres run off the side of the screen, so the first one
 * chosen is slid into view when the page opens with it, or a tag chooses
 * it, rather than leaving the reader to wonder why they only see music.
 */
const genreRow = ref<HTMLElement | null>(null);

const revealChosenGenre = (): void => {
    const chosen = genreRow.value?.querySelector<HTMLElement>(
        'button[aria-pressed="true"]:not(:first-child)',
    );

    if (genreRow.value && chosen) {
        genreRow.value.scrollLeft =
            chosen.offsetLeft - genreRow.value.offsetLeft - 20;
    }
};

onMounted(async () => {
    // Measured in the site's own font: the pills are wider until it arrives.
    await document.fonts.ready;
    revealChosenGenre();
});

/**
 * The tag on a listing narrows the page to that genre alone, and goes back
 * up to the row so the reader can see what they have chosen.
 */
const showGenreRow = computed<boolean>(
    () => !profileOwner.value && (props.genres?.length ?? 0) > 0,
);

provideGenreTags(
    computed<GenreTags | null>(() =>
        showGenreRow.value
            ? {
                  href: (slug) =>
                      new URL(page.url, 'https://listings.invalid').pathname +
                      genreQuery([slug]),
                  choose: (slug) => {
                      chooseGenres([slug], () =>
                          nextTick(revealChosenGenre),
                      );
                      window.scrollTo({
                          top: 0,
                          behavior: window.matchMedia(
                              '(prefers-reduced-motion: reduce)',
                          ).matches
                              ? 'auto'
                              : 'smooth',
                      });
                  },
              }
            : null,
    ),
);

/**
 * How the genres chosen read in a sentence: "Film", "Film or Quiz",
 * "Dance, Film or Quiz".
 */
const chosenGenreNames = computed<string>(() => {
    const names = chosenGenres.value.map(({ name }) => name);

    return names.length > 1
        ? `${names.slice(0, -1).join(', ')} or ${names.at(-1)}`
        : (names[0] ?? '');
});

const genreListings = computed(() =>
    chosenSlugs.value.length > 0
        ? resolvedListings.value.filter((listing) =>
              isChosen(listing.category?.slug ?? ''),
          )
        : resolvedListings.value,
);

/**
 * Each genre is the tag a listing wears, lit as that tag is when pointed
 * at once it is the one chosen.
 */
const genrePillClass = (chosen: boolean): string =>
    chosen
        ? 'bg-hivis text-tarmac'
        : 'bg-tarmac text-white hover:bg-hivis hover:text-tarmac';

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
 * Text as the search compares it: lowercased, with its accents and apostrophes
 * taken off, so "tres" finds "Très", "très" finds "Tres", and "tomorrows" finds
 * "Tomorrow’s". Splitting each accented letter into the letter and its mark is
 * what lets the mark be dropped.
 */
const foldForSearch = (text: string): string =>
    text
        .normalize('NFD')
        .replace(/[\p{M}'’‘ʼ]/gu, '')
        .toLowerCase();

/**
 * The artists on a listing's bill, often not named in its title. Stored in
 * Nova's Flexible shape, as a JSON string or already decoded.
 */
const lineUpNames = (listing: Listing): (string | undefined)[] => {
    const stored = listing.line_up;
    const groups: { attributes?: Partial<LineUpArtist> }[] =
        typeof stored === 'string' ? JSON.parse(stored) : (stored ?? []);

    return groups.map((group) => group.attributes?.name);
};

/**
 * Everything a row can be found by, in one folded string each.
 *
 * Built per listing rather than per keystroke: the whole list is already
 * in the page, so the only cost worth avoiding is doing this work again
 * for every letter typed.
 */
const searchable = computed(() =>
    genreListings.value.map((listing) => ({
        listing,
        haystack: foldForSearch(
            [
                listing.title,
                ...lineUpNames(listing),
                listing.venue?.name,
                listing.venue?.address,
                listing.venue?.town,
                listing.venue?.postcode,
                listing.promoter?.name,
                listing.category?.name,
                listing.ticket_price,
                formatListingDates(listing),
            ]
                .filter(Boolean)
                .join(' '),
        ),
    })),
);

/**
 * Every word typed has to appear somewhere in the row, in any order and
 * across any of the fields, so "sub rooms jazz" finds a jazz night at
 * The Sub Rooms without anybody having to guess the word order.
 */
const visibleListings = computed(() => {
    const words = foldForSearch(search.value)
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    if (words.length === 0) {
        return genreListings.value;
    }

    return searchable.value
        .filter(({ haystack }) =>
            words.every((word) => haystack.includes(word)),
        )
        .map(({ listing }) => listing);
});

const filtering = computed<boolean>(() => search.value.trim().length > 0);

/**
 * The heading a listing goes under, when this page heads its list by day:
 * none on a page about a single day, nor for a listing sent without the
 * day it was ordered by.
 */
function headingFor(listing: Listing): ListingHeading | null {
    if (!props.dayHeadings || !listing.next_day) {
        return null;
    }

    return listingHeading(listing.next_day, props.currentDay, props.firstDay);
}

/*
 * The listings as they are laid out.
 *
 * A heading goes in over the first listing of each day, or of each month
 * further out, from what is left after search, genres and emptied hearts,
 * so a day with nothing to show has no heading either. Its key is its day,
 * so it fades out with the last of its listings and back in with the first.
 *
 * An advert from the live line-up goes in after every so many listings
 * (advertEvery, from LISTINGS_ADVERT_EVERY), counting listings alone, and
 * never as the last row. Coming after a listing, it can stand before a
 * heading but never between a heading and its first listing. The adverts
 * go in turn from the one after the advert this page was given, so none
 * is shown twice running and, as the page's own advert rotates, the slots
 * share out evenly.
 */
type Row =
    | { kind: 'heading'; key: string; label: string }
    | { kind: 'listing'; key: string; listing: Listing }
    | { kind: 'advert'; key: string; advert: Advert };

const advertLineUp = useAdvertLineUp();

const rows = computed<Row[]>(() => {
    const every = page.props.advertEvery ?? 0;
    const lineUp = advertLineUp.value;
    const listings = visibleListings.value;
    const laidOut: Row[] = [];
    let slot = 0;
    let headedBy: string | null = null;

    listings.forEach((listing, index) => {
        const heading = headingFor(listing);

        if (heading && heading.key !== headedBy) {
            laidOut.push({
                kind: 'heading',
                key: `heading-${heading.key}`,
                label: heading.label,
            });
            headedBy = heading.key;
        }

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
watch([search, chosenSlugs], () => revealAll());
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
            <Link :href="withGenre('/listings/')">
                <strong v-if="props.all">ALL</strong>
                <span v-else>ALL</span>
            </Link>
            &nbsp;|&nbsp;
            <Link :href="withGenre('/listings/today')">
                <strong v-if="props.today">TODAY</strong>
                <span v-else>TODAY</span>
            </Link>
            &nbsp;|&nbsp;

            <Link :href="withGenre('/listings/thisweek/')">
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
                <Link
                    :href="withGenre('/listings/bookmarked/')"
                    class="whitespace-nowrap"
                >
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
        One line of genres on a phone, slid along with a thumb rather than
        stacked four deep over the listings; wrapped where there is room.
        Each pill turns its genre on and off, and any number can be on at
        once; All turns them all off. Those on are lit as the tag on a
        listing is when pointed at.
    -->
    <div
        v-if="showGenreRow"
        class="border-b border-silver py-4 md:border-l md:px-5"
    >
        <div
            ref="genreRow"
            role="group"
            aria-label="Genre"
            class="flex gap-2 overflow-x-auto px-5 [scrollbar-width:none] md:flex-wrap md:overflow-visible md:px-0"
        >
            <button
                type="button"
                :aria-pressed="chosenGenres.length === 0"
                class="shrink-0 cursor-pointer rounded-sm px-4 py-2 font-din text-sm whitespace-nowrap uppercase md:text-xs"
                :class="genrePillClass(chosenGenres.length === 0)"
                @click="chooseGenres([])"
            >
                All
            </button>
            <button
                v-for="choice in props.genres"
                :key="choice.slug"
                type="button"
                :aria-pressed="isChosen(choice.slug)"
                class="shrink-0 cursor-pointer rounded-sm px-4 py-2 font-din text-sm whitespace-nowrap uppercase md:text-xs"
                :class="genrePillClass(isChosen(choice.slug))"
                @click="toggleGenre(choice.slug)"
            >
                {{ choice.name }}
            </button>
        </div>
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
                    placeholder="Search by town, venue, promoter, genre or title"
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
    <div>
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
            <TransitionGroup
                name="listing"
                v-on="props.bookmarked ? collapseOnLeave : {}"
            >
                <div v-for="row in rows" :key="row.key">
                    <ListingAdvert
                        v-if="row.kind === 'advert'"
                        :advert="row.advert"
                    />
                    <h2
                        v-else-if="row.kind === 'heading'"
                        class="listing-heading px-5 pt-6 pb-4 font-din text-xl font-bold uppercase"
                    >
                        {{ row.label }}
                    </h2>
                    <ListingRow v-else :listing="row.listing" />
                </div>
            </TransitionGroup>
        </div>
    </div>

    <div
        v-if="visibleListings.length === 0"
        class="border-silver px-5 py-5 md:border-l"
    >
        <h1 v-if="filtering" class="font-bold uppercase">
            Nothing matches &ldquo;{{ search.trim() }}&rdquo;
        </h1>
        <h1 v-else-if="chosenGenres.length" class="font-bold uppercase">
            No {{ chosenGenreNames }} events listed
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
 * outlive what they were dividing. On My Bookmarks it closes up as it fades,
 * as only one leaves at a time there, not a whole search's worth.
 */
.listing-enter-active,
.listing-leave-active {
    transition:
        opacity 0.25s ease,
        height 0.25s ease;
}

@media (prefers-reduced-motion: reduce) {
    .listing-enter-active,
    .listing-leave-active {
        transition: none;
    }
}

.listing-enter-from,
.listing-leave-to {
    opacity: 0;
}
</style>
