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
    User,
} from '@lucide/vue';
import { computed } from 'vue';
import BookmarkController from '@/actions/App/Http/Controllers/Dashboard/BookmarkController';
import ListingController from '@/actions/App/Http/Controllers/Dashboard/ListingController';
import OrderController from '@/actions/App/Http/Controllers/Dashboard/OrderController';
import PromoterController from '@/actions/App/Http/Controllers/Dashboard/PromoterController';
import VenueController from '@/actions/App/Http/Controllers/Dashboard/VenueController';
import WidgetController from '@/actions/App/Http/Controllers/Dashboard/WidgetController';
import { dashboard, home, logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { type Managed, type NavGroup, type NavItem } from '@/types';
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
} from './ui/sidebar';

const page = usePage();

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
 * public site is always a click away, with what you follow and have paid
 * for, then each thing you run with its own listings under it. What you hear from us is a setting rather than a
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
                {
                    title: 'My Bookmarks',
                    href: BookmarkController.index(),
                    icon: Bookmark,
                },
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
                /*
                 * Orders is offered only to an account that has some.
                 * Nothing records an order yet, so today that is nobody -
                 * the entry is here rather than waiting to be remembered.
                 */
                ...(page.props.auth.hasOrders
                    ? [
                          {
                              title: 'Orders',
                              href: OrderController.index(),
                              icon: Receipt,
                          },
                      ]
                    : []),
            ],
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
 * Staff get a way back to the office, which is not an Inertia page and so
 * is followed as an ordinary link.
 */
const footerNavItems = computed<NavItem[]>(() => [
    {
        title: 'Profile',
        href: editProfile(),
        icon: User,
    },
    {
        title: 'Support',
        href: 'mailto:alex@goodonpaper.info',
        icon: Mail,
    },
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
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
