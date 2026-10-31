<script setup lang="ts">
import {
    ArrowUturnLeftIcon,
    ClipboardDocumentCheckIcon,
    CubeIcon,
    ShoppingBagIcon,
} from '@heroicons/vue/24/outline';
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed, nextTick, onMounted, onUnmounted, watch } from 'vue';
import type { Ref } from 'vue';
import { show as basketShow } from '@/actions/App/Http/Controllers/BasketController';
import { index as ordersIndex } from '@/actions/App/Http/Controllers/Dashboard/OrderController';
import { category as shopCategoryRoute } from '@/actions/App/Http/Controllers/ShopController';
import { dashboard, switchBack } from '@/routes';

interface Props {
    magazineSection?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    magazineSection: false,
});

const page = usePage();

/** Where the Office is, for the people let in, and null for anyone else. */
const office = computed(() => page.props.auth?.office ?? null);

/**
 * Whether to offer Orders here: to somebody who has bought from the shop
 * but has no dashboard, whose sidebar would otherwise be the only way in.
 */
const showOrders = computed(
    () => !!page.props.auth?.hasOrders && !hasDashboard.value,
);

/** How many things are in the basket: nought while the shop is closed. */
const basketCount = computed(() => page.props.basket?.count ?? 0);

/** Who switched to this account from the Office, while they are in it. */
const switchedFrom = computed(() => page.props.auth?.switchedFrom ?? null);

/**
 * Whether this account has a dashboard: it holds a role, as staff all do.
 * Somebody signed up only to keep bookmarks has nothing there yet.
 */
const hasDashboard = computed(
    () =>
        !!page.props.auth?.user &&
        Object.values(page.props.auth.roles ?? {}).some(Boolean),
);

const showMenu = ref(false);

/*
 * The second tier of the menu belongs to whichever section has one:
 * the Magazine, or the Shop with its shelves. On desktop it shows the
 * section being hovered, or failing that the section the page is in. On a
 * phone, which has no hover, tapping the section opens it as a panel.
 */
type Section = 'magazine' | 'shop';

/** The panel open on a phone, if any. */
const showSubMenu = ref<Section | null>(null);
/** The last panel opened on a phone, kept while it slides away. */
const phoneSection = ref<Section>('magazine');
/** The section being hovered on desktop. */
const hovering = ref<Section | null>(null);
let closeTimer: ReturnType<typeof setTimeout> | null = null;

const shopCategories = computed(() => page.props.shopCategories ?? []);
const shopHasSubNav = computed(() => shopCategories.value.length > 0);

const inShopSection = computed(() =>
    ['/shop', '/basket', '/checkout'].some((section) =>
        page.url.startsWith(section),
    ),
);

const openSubNav = (section: Section) => {
    if (section === 'shop' && !shopHasSubNav.value) {
        return;
    }
    if (closeTimer) clearTimeout(closeTimer);
    hovering.value = section;
};
const scheduleCloseSubNav = () => {
    closeTimer = setTimeout(() => (hovering.value = null), 150);
};

/** The section whose second tier the page is in, if any. */
const currentSection = computed<Section | null>(() => {
    if (props.magazineSection) {
        return 'magazine';
    }

    return inShopSection.value && shopHasSubNav.value ? 'shop' : null;
});

const desktopSection = computed(() => hovering.value ?? currentSection.value);

const subNavVisible = computed(() => desktopSection.value !== null);

const isDesktop = ref(true);

/** Which section's links the second tier holds right now. */
const subNavSection = computed<Section>(() =>
    isDesktop.value ? (desktopSection.value ?? 'magazine') : phoneSection.value,
);

interface SubNavLink {
    label: string;
    href: string;
    /** Only on a phone, where tapping the section opens this list instead. */
    phoneOnly?: boolean;
    /** Marked when the page is this link's or one beneath it. */
    markCurrent?: boolean;
}

const magazineLinks: SubNavLink[] = [
    { label: 'Features.', href: '/magazine/features' },
    { label: 'Advertise.', href: '/advertise' },
    { label: 'Archives.', href: '/magazine/archives' },
    { label: 'Who we are.', href: '/who-we-are' },
    { label: 'Stockists.', href: '/magazine/stockists' },
];

const subNavLinks = computed<SubNavLink[]>(() =>
    subNavSection.value === 'shop'
        ? [
              { label: 'Everything.', href: '/shop', phoneOnly: true },
              ...shopCategories.value.map((category) => ({
                  label: `${category.name}.`,
                  href: shopCategoryRoute.url(category.slug),
                  markCurrent: true,
              })),
          ]
        : magazineLinks,
);

const isCurrent = (link: SubNavLink): boolean => {
    const path = page.url.split('?')[0];

    return (
        !!link.markCurrent &&
        (path === link.href || path.startsWith(`${link.href}/`))
    );
};

const word = computed(() => {
    const words = ['PRINTED', 'SPOKEN', 'DIGITAL', 'WRITTEN'];
    return words[Math.floor(Math.random() * words.length)];
});

const DESKTOP_BREAKPOINT = 765; // adjust to match your CSS breakpoint

const hasInteracted = ref(false);

const toggleNav = () => {
    hasInteracted.value = true;
    showMenu.value = !showMenu.value;
    showSubMenu.value = null;
};
const toggleSubNav = (section: Section) => {
    hasInteracted.value = true;

    if (showSubMenu.value === section) {
        showSubMenu.value = null;

        return;
    }

    phoneSection.value = section;
    showSubMenu.value = section;
};
const closeSubNav = () => {
    showSubMenu.value = null;
};

const handleResize = () => {
    isDesktop.value = window.innerWidth >= DESKTOP_BREAKPOINT;

    if (isDesktop.value) {
        showMenu.value = false;
        showSubMenu.value = null;
        hasInteracted.value = false; // re-arm for the next shrink
    }
};

/** How long a phone panel takes to slide, as its transition says. */
const PANEL_SLIDE_MS = 500;

/*
 * Safari on iOS 26 tints its see-through toolbars from fixed elements at
 * the screen's edges. It takes the colour only when such an element appears
 * with none there before, as the yellow menu does sliding in, and then holds
 * it until the page next scrolls, whatever else comes and goes at the edges.
 * At the very top of a page, with its toolbars at full size, it tints from
 * the page itself instead, and the page shows below the panels, under the
 * bottom toolbar, where fixed elements are not drawn.
 *
 * So while a panel is open the page behind is painted the panel's colour,
 * and once the panel has slid in its content is hidden, leaving only that
 * colour. When a sub-menu has slid over the menu, or slid away again to show
 * it, the panel now in view is also taken out for a couple of frames and put
 * back, so that Safari sees it arrive afresh and takes its colour.
 *
 * When everything shuts, two invisible strips along the top and bottom edges
 * arrive the same way, and hand the bars back the page's own colour.
 */
type BarTint = 'menu' | 'subMenu' | 'page';

const pageColours: Record<BarTint, string> = {
    menu: 'var(--color-hivis)',
    subMenu: 'black',
    page: '',
};

/** Whether the strips handing Safari's bars back the page colour are up. */
const pageTintShown = ref(false);
let barTintTimer: ReturnType<typeof setTimeout> | null = null;

/** How long the page colour is held once the panels have slid away. */
const PAGE_TINT_MS = 300;

/** Paint the page behind the panels, or give it back its own colour. */
const paintPage = (colour: string) => {
    document.documentElement.style.backgroundColor = colour;
    document.body.style.backgroundColor = colour;
};

/**
 * Hide the page's content behind an open panel, leaving its background.
 * The panels and the menu button stay in sight, as they are max-md:visible.
 */
const hidePage = (hidden: boolean) => {
    document.body.style.visibility = hidden ? 'hidden' : '';
};

/**
 * Take a full-screen panel out of the page for two frames and put it back,
 * so that Safari sees it arrive and tints its bars from it. The page is
 * already hidden behind it and painted its colour, so nothing seems to blink.
 */
const retintFromPanel = async (parked: Ref<boolean>) => {
    parked.value = true;

    await nextTick();
    await new Promise((resolve) =>
        requestAnimationFrame(() => requestAnimationFrame(resolve)),
    );

    parked.value = false;
};

/**
 * A full-screen panel on a phone, taken out of the page whenever it is shut.
 *
 * Shut, the panels used to wait just off the right edge, still fixed over
 * the whole screen. Safari on iOS 26 tints its see-through toolbars from
 * fixed elements at the screen's edges, so once the yellow menu had been
 * opened the bars stayed yellow after it slid away. So a shut panel is
 * parked (display: none below md, where the desktop menu is untouched), and
 * put back, drawn off-screen and only then slid in when it opens.
 */
const usePhonePanel = (isOpen: () => boolean) => {
    const panel = ref<HTMLElement | null>(null);
    const parked = ref(!isOpen());
    const slidIn = ref(isOpen());
    let parkTimer: ReturnType<typeof setTimeout> | null = null;

    watch(isOpen, async (open) => {
        if (parkTimer) clearTimeout(parkTimer);

        if (!open) {
            slidIn.value = false;
            parkTimer = setTimeout(() => (parked.value = true), PANEL_SLIDE_MS);

            return;
        }

        parked.value = false;
        await nextTick();
        panel.value?.getBoundingClientRect();

        if (isOpen()) slidIn.value = true;
    });

    return { panel, parked, slidIn };
};

const {
    panel: menuPanel,
    parked: menuParked,
    slidIn: menuSlidIn,
} = usePhonePanel(() => showMenu.value);
const {
    panel: subMenuPanel,
    parked: subMenuParked,
    slidIn: subMenuSlidIn,
} = usePhonePanel(() => showSubMenu.value !== null);

/**
 * Whether the menu is tucked away behind a sub-menu that has finished
 * sliding over it, so that the sub-menu is the only panel at the edges.
 */
const menuCovered = ref(false);

watch(
    (): BarTint =>
        showSubMenu.value ? 'subMenu' : showMenu.value ? 'menu' : 'page',
    (tint, previousTint) => {
        if (barTintTimer) clearTimeout(barTintTimer);

        pageTintShown.value = false;
        paintPage(pageColours[tint]);

        if (tint !== 'subMenu') {
            menuCovered.value = false;
        }

        if (tint === 'page') {
            hidePage(false);
        }

        barTintTimer = setTimeout(() => {
            if (tint === 'page') {
                pageTintShown.value = true;
                barTintTimer = setTimeout(
                    () => (pageTintShown.value = false),
                    PAGE_TINT_MS,
                );

                return;
            }

            hidePage(true);

            if (tint === 'subMenu') {
                menuCovered.value = true;
                retintFromPanel(subMenuParked);
            } else if (previousTint === 'subMenu') {
                retintFromPanel(menuParked);
            }
        }, PANEL_SLIDE_MS);
    },
);

onUnmounted(() => {
    paintPage('');
    hidePage(false);
});

/*
 * On desktop the header scrolls away with the page and comes back when the
 * reader scrolls up, so it is to hand without sitting over what they are
 * reading.
 *
 * Near the top of the page it simply lies in the page and scrolls off
 * like the rest. Scrolled up from further down, it is pinned to the top
 * and eases in; scrolled down again, it slips back out. Once pinned it
 * stays pinned until the page is back at the very top, where pinned and
 * unpinned look the same, so letting go never moves it.
 */
const header = ref<HTMLElement | null>(null);
const pinned = ref(false);
const shown = ref(false);
const sliding = ref(false);
let lastScrollY = 0;

/** Scrolls smaller than this are a trackpad settling, not a change of mind. */
const SCROLL_TOLERANCE = 4;

/**
 * Pin the header out of sight, then ease it in. The hidden position has to
 * be drawn before the transition is switched on, or the browser has nothing
 * to animate from and the header appears in one jump. The style is flushed
 * by hand rather than a frame waited for, so a scroll event arriving in
 * between cannot show it early.
 */
const pinAndReveal = async () => {
    sliding.value = false;
    shown.value = false;
    pinned.value = true;

    await nextTick();
    header.value?.getBoundingClientRect();

    sliding.value = true;
    shown.value = true;
};

const unpin = () => {
    sliding.value = false;
    shown.value = false;
    pinned.value = false;
};

const handleScroll = () => {
    const scrollY = Math.max(window.scrollY, 0);
    const distance = scrollY - lastScrollY;

    if (scrollY <= 0) {
        lastScrollY = 0;
        unpin();

        return;
    }

    if (Math.abs(distance) < SCROLL_TOLERANCE) {
        return;
    }

    lastScrollY = scrollY;
    const headerHeight = header.value?.offsetHeight ?? 0;

    if (distance > 0) {
        // Kept while the page is still within the header's own height, so
        // it does not leave a gap where it would have been.
        if (pinned.value && scrollY > headerHeight) {
            shown.value = false;
        }

        return;
    }

    if (pinned.value) {
        shown.value = true;
    } else if (scrollY > headerHeight) {
        pinAndReveal();
    }
};

/*
 * It comes in slowly, settling at the end, and goes out more briskly,
 * picking up speed: arriving is something to notice, leaving is not.
 */
const headerPosition = computed(() => [
    pinned.value ? 'md:sticky md:top-0' : 'md:relative',
    pinned.value && !shown.value ? 'md:-translate-y-full' : '',
    sliding.value ? 'motion-safe:md:transition-[translate]' : '',
    sliding.value && shown.value
        ? 'motion-safe:md:duration-500 motion-safe:md:ease-[cubic-bezier(0.22,1,0.36,1)]'
        : '',
    sliding.value && !shown.value
        ? 'motion-safe:md:duration-300 motion-safe:md:ease-[cubic-bezier(0.4,0,1,1)]'
        : '',
]);

onMounted(() => {
    isDesktop.value = window.innerWidth >= DESKTOP_BREAKPOINT;
    lastScrollY = window.scrollY;
    window.addEventListener('resize', handleResize);
    window.addEventListener('scroll', handleScroll, { passive: true });
});
onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
    window.removeEventListener('scroll', handleScroll);
    if (closeTimer) clearTimeout(closeTimer);
});
</script>

<template>
    <div
        ref="header"
        :class="headerPosition"
        class="m-0 border-b border-silver md:z-30 md:w-full md:bg-surface"
    >
        <nav class="sm:h-full md:flex md:items-center">
            <div
                :class="
                    office || hasDashboard || showOrders || switchedFrom
                        ? 'pt-8 pb-4'
                        : 'py-8'
                "
                class="flex items-center md:pr-13.5"
            >
                <div class="flex flex-col items-start gap-2 md:pr-15">
                    <Link
                        href="/"
                        class="pl-5 font-haas text-xl/4 font-bold text-black hover:text-gray-600 md:text-2xl/5 dark:text-white"
                        >GOOD<br />ON<br />PAPER
                    </Link>
                    <!--
                        The ways in for signed-in accounts: the Office for
                        the people let in, and the dashboard for anyone with
                        a role. Set close together, with less padding under
                        them, so they add little to the masthead's height.
                    -->
                    <div
                        v-if="
                            office || hasDashboard || showOrders || switchedFrom
                        "
                        class="ml-5 flex flex-col items-start gap-0.5"
                    >
                        <!-- A plain link: the Office is Nova, not one of the site's own pages. -->
                        <a
                            v-if="office"
                            :href="office"
                            class="inline-flex items-center gap-1.5 font-din text-xs/4 text-tyre uppercase hover:text-black dark:hover:text-white"
                        >
                            <CubeIcon class="size-4" aria-hidden="true" />
                            Office
                        </a>
                        <Link
                            v-if="hasDashboard"
                            :href="dashboard()"
                            class="inline-flex items-center gap-1.5 font-din text-xs/4 text-tyre uppercase hover:text-black dark:hover:text-white"
                        >
                            <ClipboardDocumentCheckIcon
                                class="size-4"
                                aria-hidden="true"
                            />
                            Dashboard
                        </Link>
                        <Link
                            v-if="showOrders"
                            :href="ordersIndex()"
                            class="inline-flex items-center gap-1.5 font-din text-xs/4 text-tyre uppercase hover:text-black dark:hover:text-white"
                        >
                            <ShoppingBagIcon
                                class="size-4"
                                aria-hidden="true"
                            />
                            Orders
                        </Link>
                        <!-- Staff in an account switched to from the Office. -->
                        <Link
                            v-if="switchedFrom"
                            :href="switchBack()"
                            as="button"
                            class="inline-flex cursor-pointer items-center gap-1.5 font-din text-xs/4 text-tyre uppercase hover:text-black dark:hover:text-white"
                        >
                            <ArrowUturnLeftIcon
                                class="size-4"
                                aria-hidden="true"
                            />
                            Switch back
                        </Link>
                    </div>
                </div>
                <!-- Mobile menu button -->
                <div
                    @click="toggleNav"
                    class="fixed top-5 right-5 z-50 flex rounded-full bg-hivis p-2 pt-3 pb-3 max-md:visible md:hidden!"
                >
                    <button
                        type="button"
                        :aria-expanded="showMenu"
                        aria-label="Toggle menu"
                        class="text-black hover:text-gray-600 focus:text-gray-600 focus:outline-none"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="19.386"
                            height="12"
                            viewBox="0 0 19.386 12"
                            class="overflow-visible"
                        >
                            <line
                                x1="0"
                                y1="1"
                                x2="19.386"
                                y2="1"
                                stroke="#343434"
                                stroke-width="2"
                                class="origin-center transition-transform duration-300 ease-in-out [transform-box:fill-box]"
                                :class="{
                                    'translate-y-[5px] rotate-45': showMenu,
                                }"
                            />
                            <line
                                x1="0"
                                y1="11"
                                x2="19.386"
                                y2="11"
                                stroke="#343434"
                                stroke-width="2"
                                class="origin-center transition-transform duration-300 ease-in-out [transform-box:fill-box]"
                                :class="{
                                    '-translate-y-[5px] -rotate-45': showMenu,
                                }"
                            />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Invisible strips handing Safari's toolbars the page colour -->
            <template v-if="pageTintShown">
                <div
                    aria-hidden="true"
                    class="pointer-events-none fixed inset-x-0 top-0 z-50 h-px bg-surface opacity-0 md:hidden"
                />
                <div
                    aria-hidden="true"
                    class="pointer-events-none fixed inset-x-0 bottom-0 z-50 h-px bg-surface opacity-0 md:hidden"
                />
            </template>

            <!-- Wrapper: invisible on mobile, stacks the two tiers on desktop -->
            <div class="md:flex md:flex-col md:gap-y-1.5">
                <!-- Main menu -->
                <ul
                    ref="menuPanel"
                    :class="[
                        menuSlidIn
                            ? 'translate-x-0'
                            : 'pointer-events-none translate-x-[100%]',
                        menuParked || menuCovered ? 'max-md:hidden' : '',
                        hasInteracted
                            ? 'motion-safe:transition-[translate] motion-safe:duration-500 motion-safe:ease-out'
                            : '',
                    ]"
                    class="fixed inset-0 z-40 mb-0 flex h-screen flex-col space-y-6 bg-hivis p-10 text-xl max-md:visible md:pointer-events-auto md:static! md:mt-0 md:h-auto! md:translate-x-0 md:flex-row! md:space-y-0 md:gap-x-6 md:bg-transparent! md:p-0! md:transition-none"
                >
                    <li
                        class="cursor-pointer font-haas text-[30px] leading-[31px] font-semibold text-black hover:text-gray-600 md:hidden"
                    >
                        <Link href="/">Home.</Link>
                    </li>
                    <li
                        @mouseenter="openSubNav('magazine')"
                        @mouseleave="scheduleCloseSubNav"
                        class="text-tighten cursor-pointer font-haas text-[30px] leading-[31px] font-semibold text-black hover:text-gray-600 md:text-xl md:font-semibold! dark:hover:text-gray-400 dark:md:text-white"
                    >
                        <!--
                            On desktop, hovering shows the sub-nav, so a
                            click goes to the features. A phone has no
                            hover, so there a tap opens the sub-nav.
                        -->
                        <Link href="/magazine/features" class="hidden md:inline"
                            >Magazine.</Link
                        >
                        <span
                            class="md:hidden"
                            @click="toggleSubNav('magazine')"
                            >Magazine.</span
                        >
                        <span
                            @click="toggleSubNav('magazine')"
                            class="float-right md:hidden"
                            >></span
                        >
                    </li>
                    <li
                        class="text-tighten cursor-pointer font-haas text-[30px] leading-[31px] font-semibold text-black hover:text-gray-600 md:text-xl md:font-semibold! dark:hover:text-gray-400 dark:md:text-white"
                    >
                        <Link href="/listings">Listings.</Link>
                    </li>
                    <li
                        @mouseenter="openSubNav('shop')"
                        @mouseleave="scheduleCloseSubNav"
                        class="text-tighten cursor-pointer font-haas text-[30px] leading-[31px] font-semibold text-black hover:text-gray-600 md:text-xl md:font-semibold! dark:hover:text-gray-400 dark:md:text-white"
                    >
                        <!--
                            As Magazine: with shelves to show, a phone opens
                            them with a tap; without, it is a plain link.
                        -->
                        <Link
                            href="/shop"
                            :class="shopHasSubNav ? 'hidden md:inline' : ''"
                            >Shop.</Link
                        >
                        <span
                            v-if="shopHasSubNav"
                            class="md:hidden"
                            @click="toggleSubNav('shop')"
                            >Shop.</span
                        >
                        <Link
                            v-if="basketCount"
                            :href="basketShow()"
                            class="ml-1 inline-flex min-w-6 items-center justify-center rounded-full bg-tarmac px-1.5 align-middle text-sm leading-6 text-white dark:bg-white dark:text-black"
                            :aria-label="`Basket, ${basketCount} ${basketCount === 1 ? 'item' : 'items'}`"
                            >{{ basketCount }}</Link
                        >
                        <span
                            v-if="shopHasSubNav"
                            @click="toggleSubNav('shop')"
                            class="float-right md:hidden"
                            >></span
                        >
                    </li>
                    <li
                        class="text-tighten cursor-pointer font-haas text-[30px] leading-[31px] font-semibold text-black hover:text-gray-600 md:text-xl md:font-semibold! dark:hover:text-gray-400 dark:md:text-white"
                    >
                        <Link href="/subscribe">Subscribe.</Link>
                    </li>
                    <li
                        class="mt-15 cursor-pointer font-haas text-xl leading-6 font-semibold text-black hover:text-gray-600 md:hidden"
                    >
                        <Link href="/dashboard/">Account.</Link>
                    </li>
                    <li class="mt-auto md:hidden">
                        <p
                            class="font-haas text-[20px]/[18px] font-bold tracking-tight text-black"
                        >
                            LONG<br />LIVE THE<br />{{ word }}<br />WORD
                        </p>
                    </li>
                </ul>

                <!-- Submenu: full-screen card on mobile, second tier on desktop -->
                <ul
                    @mouseenter="hovering && openSubNav(hovering)"
                    @mouseleave="scheduleCloseSubNav"
                    ref="subMenuPanel"
                    :class="[
                        subMenuSlidIn
                            ? 'translate-x-0'
                            : 'pointer-events-none translate-x-[100%]',
                        subMenuParked ? 'max-md:hidden' : '',
                        subNavVisible
                            ? 'md:visible md:opacity-100'
                            : 'md:invisible md:opacity-0',
                        hasInteracted
                            ? 'motion-safe:transition-[translate] motion-safe:duration-500 motion-safe:ease-out'
                            : '',
                    ]"
                    class="fixed inset-0 z-40 flex h-screen flex-col space-y-6 bg-black p-5 text-white max-md:visible md:pointer-events-auto md:static! md:h-auto! md:translate-x-0 md:flex-row! md:space-y-0! md:gap-x-6 md:bg-transparent! md:p-0! motion-safe:md:transition-opacity motion-safe:md:duration-200"
                >
                    <!-- Back button, mobile only -->
                    <li
                        @click="closeSubNav"
                        class="cursor-pointer font-haas text-[30px] leading-[31px] font-semibold text-white hover:text-gray-500 md:hidden"
                    >
                        &lt;
                    </li>
                    <li
                        v-for="link in subNavLinks"
                        :key="link.href"
                        :class="link.phoneOnly ? 'md:hidden' : ''"
                        class="cursor-pointer font-haas text-[30px] leading-[31px] font-semibold text-white hover:text-silver md:text-base md:font-normal! md:whitespace-nowrap md:text-black! md:hover:text-gray-600! dark:md:text-white! dark:md:hover:text-silver!"
                    >
                        <Link
                            :href="link.href"
                            :class="isCurrent(link) ? 'underline' : ''"
                            :aria-current="isCurrent(link) ? 'page' : undefined"
                            >{{ link.label }}</Link
                        >
                    </li>
                </ul>
            </div>
        </nav>
    </div>
</template>

<style scoped></style>
