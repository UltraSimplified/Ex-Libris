<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bookmark,
    BookOpen,
    Box,
    LogOut,
    Mail,
    ScrollText,
    House,
    Megaphone,
    PanelsTopLeft,
    Receipt,
} from '@lucide/vue';
import { computed, nextTick } from 'vue';
import ListingController from '@/actions/App/Http/Controllers/Dashboard/ListingController';
import OrderController from '@/actions/App/Http/Controllers/Dashboard/OrderController';
import PromoterController from '@/actions/App/Http/Controllers/Dashboard/PromoterController';
import VenueController from '@/actions/App/Http/Controllers/Dashboard/VenueController';
import WidgetController from '@/actions/App/Http/Controllers/Dashboard/WidgetController';
import { useBookmarks } from '@/composables/useBookmarks';
import { dashboard, home, logout } from '@/routes';
import { type Managed, type NavGroup, type NavItem } from '@/types';
import AppearanceChooser from './AppearanceChooser.vue';
import AppLogo from './AppLogo.vue';
import NavFooter from './NavFooter.vue';
import NavMain from './NavMain.vue';
import NavUser from './NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
    useSidebar,
} from './ui/sidebar';

const page = usePage();
const { count: bookmarkCount } = useBookmarks();
const { setOpenMobile } = useSidebar();

/**
 * The panel, glided to. Already on the Dashboard there is nothing to fetch,
 * and from anywhere else the page arrives first, at its top, so the glide
 * starts from there. The address keeps the panel's anchor for anybody opening
 * the link in a new tab, which is left to the browser.
 */
function goToBookmarkedListings(event: MouseEvent): void {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();
    setOpenMobile(false);

    if (document.getElementById('bookmarked-listings')) {
        scrollToBookmarkedListings();

        return;
    }

    router.visit(dashboard().url, {
        onSuccess: () => nextTick(scrollToBookmarkedListings),
    });
}

function scrollToBookmarkedListings(): void {
    /*
     * A phone's menu holds the page still while it closes, then hands the
     * focus back to the button that opened it, at the top of the page. Either
     * would stop the glide where it began, so it waits until they are done.
     */
    if (document.querySelector('[data-mobile="true"]')) {
        requestAnimationFrame(() => setTimeout(scrollToBookmarkedListings));

        return;
    }

    const stayStill = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    document.getElementById('bookmarked-listings')?.scrollIntoView({
        behavior: stayStill ? 'auto' : 'smooth',
    });
}

/**
 * What the current address is narrowed to. The listings entries all point
 * at the same page and differ only by this, so the address alone cannot
 * say which of them is being looked at.
 */
const scope = computed<URLSearchParams>(
    () => new URLSearchParams(page.url.split('?')[1] ?? ''),
);

const onListings = computed<boolean>(() =>
    page.url.split('?')[0].startsWith(ListingController.index.url()),
);

const onWidgets = computed<boolean>(() =>
    page.url.split('?')[0].startsWith(WidgetController.index.url()),
);

/**
 * A venue or promoter, and its own listings underneath it. Most accounts
 * run one of each, so the sidebar names them rather than offering an
 * index to go through - the name is the way in to editing it, and what
 * is on there, and the widgets showing it on their own site, hang off it.
 */
const runs = (
    managed: Managed,
    icon: NavItem['icon'],
    edit: string,
    key: 'venue' | 'promoter',
    heading?: string,
): NavGroup => ({
    heading,
    items: [
        {
            title: managed.name,
            href: edit,
            icon,
            items: [
                {
                    title: 'Event Listings',
                    href: ListingController.index.url({
                        query: { [key]: managed.id },
                    }),
                    icon: ScrollText,
                    isActive:
                        onListings.value &&
                        scope.value.get(key) === String(managed.id),
                },
                {
                    title: 'Gig Widgets',
                    href: WidgetController.index.url({
                        query: { [key]: managed.id },
                    }),
                    icon: PanelsTopLeft,
                    isActive:
                        onWidgets.value &&
                        scope.value.get(key) === String(managed.id),
                },
            ],
        },
    ],
});

/*
 * The sidebar in bands, separated by a rule: the magazine itself, so the
 * public site is always a click away, with what you follow, then what you
 * have paid for, then each thing you run with its own listings under it. What you hear from us is a setting rather than a
 * section, and sits with the rest of them under Profile.
 *
 * Promoters and venues are granted by the office, so most accounts run
 * neither and those bands are simply absent. An empty band is dropped
 * along with its rule rather than leaving a gap.
 *
 * Listings are only ever reached through the venue or promoter they are
 * for. An account that runs neither is not in the business of listing
 * events - it asks to speak for a promoter first - so there is no
 * general Event Listings entry to send it to a page about nothing.
 */
const navGroups = computed<NavGroup[]>(() =>
    [
        {
            items: [
                {
                    title: 'Good On Paper',
                    href: home(),
                    icon: BookOpen,
                },
                /*
                 * The panel on the Dashboard rather than a page of its own,
                 * so the entry goes when there is nothing to scroll to, and
                 * is never lit: being on the Dashboard is not being here.
                 */
                ...(bookmarkCount.value > 0
                    ? [
                          {
                              title: 'My Bookmarks',
                              href: `${dashboard().url}#bookmarked-listings`,
                              icon: Bookmark,
                              isActive: false,
                              onSelect: goToBookmarkedListings,
                          },
                      ]
                    : []),
                ...(page.props.auth.office
                    ? [
                          {
                              title: 'Office',
                              href: page.props.auth.office,
                              icon: Box,
                              external: true,
                          },
                      ]
                    : []),
            ],
        },
        /*
         * Offered only to an account that has paid for something, and lit on
         * a purchase's own page as well as the list of them.
         */
        {
            heading: 'Purchases',
            items: page.props.auth.hasOrders
                ? [
                      {
                          title: 'View purchases',
                          href: OrderController.index(),
                          icon: Receipt,
                          isActive: page.url.startsWith(
                              OrderController.index.url(),
                          ),
                      },
                  ]
                : [],
        },
        /*
         * A publisher's widgets, of the events they have bookmarked. Only
         * for an account the office has made a publisher.
         */
        {
            heading: 'Publishing',
            items: page.props.auth.roles.is_publisher
                ? [
                      {
                          title: 'Gig Widgets',
                          href: WidgetController.index.url({
                              query: { bookmarks: 1 },
                          }),
                          icon: PanelsTopLeft,
                          isActive:
                              onWidgets.value &&
                              scope.value.get('bookmarks') === '1',
                      },
                  ]
                : [],
        },
        ...page.props.auth.promoters.map((promoter) =>
            runs(
                promoter,
                Megaphone,
                PromoterController.edit(promoter.id).url,
                'promoter',
                'Promoters',
            ),
        ),
        ...page.props.auth.venues.map((venue) =>
            runs(
                venue,
                House,
                VenueController.edit(venue.id).url,
                'venue',
                'Venues',
            ),
        ),
    ].filter((group): boolean => group.items.length > 0),
);

/**
 * Support is for the people working with the magazine: those the office has
 * made a promoter or a venue manager, which staff all are, and anybody who
 * already runs one. Somebody who only keeps bookmarks is not offered it.
 */
const offeredSupport = computed<boolean>(() => {
    const { roles, promoters, venues } = page.props.auth;

    return (
        roles.is_promoter ||
        roles.is_venue_mgr ||
        promoters.length > 0 ||
        venues.length > 0
    );
});

const footerNavItems = computed<NavItem[]>(() => [
    ...(offeredSupport.value
        ? [
              {
                  title: 'Support',
                  href: 'mailto:alex@goodonpaper.info',
                  icon: Mail,
              },
          ]
        : []),
    {
        title: 'Log out',
        href: logout(),
        icon: LogOut,
        onSelect: () => router.flushAll(),
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <template v-for="(group, index) in navGroups" :key="index">
                <SidebarSeparator v-if="index > 0" />
                <NavMain
                    :items="group.items"
                    :label="group.label"
                    :heading="group.heading"
                />
            </template>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
            <SidebarSeparator />
            <AppearanceChooser class="mt-2.5 self-center" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
