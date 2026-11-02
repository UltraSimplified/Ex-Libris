<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ListingController from '@/actions/App/Http/Controllers/Dashboard/ListingController';
import PromoterController from '@/actions/App/Http/Controllers/Dashboard/PromoterController';
import VenueController from '@/actions/App/Http/Controllers/Dashboard/VenueController';
import WidgetController from '@/actions/App/Http/Controllers/Dashboard/WidgetController';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { type BreadcrumbItem, type User } from '@/types';
import type { BookmarkedListing } from '@/types/global';
import BookmarkedListings from '../components/GOP/BookmarkedListings.vue';
import UserAvatar from '../components/UserAvatar.vue';
import AppLayout from '../layouts/AppLayout.vue';

/**
 * A venue or promoter the account runs, with its events on the site now and
 * the gig widgets it has made for its own website.
 */
interface Managed {
    id: number;
    name: string;
    eventsListed: number;
    widgets: number;
    /** A promoter's logo, as a path on the CDN; venues have none. */
    logo?: string | null;
}

const props = defineProps<{
    account: { name: string; email: string };
    venues: Managed[];
    promoters: Managed[];
    bookmarkedListings: BookmarkedListing[];
}>();

const page = usePage();

const user = computed<User>(() => page.props.auth.user);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Home',
        href: '/',
    },
];

/**
 * A panel for each venue, then each promoter, each linking to its own
 * details and its own events as the sidebar does, and to its gig widgets:
 * the list of them where it has some, or a new one, made out to it, where
 * it has none.
 */
const panels = computed(() => [
    ...props.venues.map((venue) => ({
        kind: 'Venue',
        key: `venue-${venue.id}`,
        ...venue,
        manageUrl: VenueController.edit(venue.id).url,
        eventsUrl: ListingController.index.url({
            query: { venue: venue.id },
        }),
        widgetsUrl: widgetsUrl('venue', venue),
        logoUrl: null,
    })),
    ...props.promoters.map((promoter) => ({
        kind: 'Promoter',
        key: `promoter-${promoter.id}`,
        ...promoter,
        manageUrl: PromoterController.edit(promoter.id).url,
        eventsUrl: ListingController.index.url({
            query: { promoter: promoter.id },
        }),
        widgetsUrl: widgetsUrl('promoter', promoter),
        logoUrl: promoter.logo
            ? `${String(page.props.cdnUrl).replace(/\/$/, '')}/${promoter.logo}`
            : null,
    })),
]);

function widgetsUrl(key: 'venue' | 'promoter', managed: Managed): string {
    const options = { query: { [key]: managed.id } };

    return managed.widgets > 0
        ? WidgetController.index.url(options)
        : WidgetController.create.url(options);
}

const eventsListedLabel = (count: number): string =>
    count === 1 ? 'Event listed' : 'Events listed';
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!--
                Fixed at 250 square rather than sized in rems: this is one
                image at one size, and a viewer's text size should not
                decide how big their own face is.
            -->
            <div class="flex justify-center pt-4 pb-6">
                <UserAvatar
                    :user="user"
                    badge
                    class="h-[250px] w-[250px] rounded-full border-[6px] border-hivis"
                    fallback-class="rounded-full text-6xl font-semibold"
                />
            </div>

            <!-- One column at every width, as on a phone. -->
            <div class="mx-auto flex w-full max-w-2xl flex-col gap-4">
                <section
                    class="dashboard-panel rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
                >
                    <dl class="space-y-1">
                        <div>
                            <dt class="inline font-semibold">Name:</dt>
                            {{ ' ' }}
                            <dd class="inline">{{ account.name }}</dd>
                        </div>
                        <div>
                            <dt class="inline font-semibold">Email:</dt>
                            {{ ' ' }}
                            <dd class="inline break-all">
                                {{ account.email }}
                            </dd>
                        </div>
                    </dl>
                    <Link
                        :href="editProfile()"
                        class="mt-4 inline-block underline underline-offset-4"
                    >
                        Manage profile
                    </Link>
                </section>

                <section
                    v-for="panel in panels"
                    :key="panel.key"
                    class="dashboard-panel flex items-center justify-between gap-6 rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
                >
                    <div class="min-w-0">
                        <p
                            class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                        >
                            {{ panel.kind }}
                        </p>
                        <h2 class="mt-1 text-xl font-semibold">
                            {{ panel.name }}
                        </h2>
                        <div class="mt-4 flex flex-col items-start gap-2">
                            <Link
                                :href="panel.manageUrl"
                                class="underline underline-offset-4"
                            >
                                Manage {{ panel.kind.toLowerCase() }}
                            </Link>
                            <Link
                                :href="panel.eventsUrl"
                                class="underline underline-offset-4"
                            >
                                Manage events
                            </Link>
                            <Link
                                :href="panel.widgetsUrl"
                                class="underline underline-offset-4"
                            >
                                {{
                                    panel.widgets > 0
                                        ? 'Manage your website listing widgets'
                                        : 'Create a listings widget to use on your website'
                                }}
                            </Link>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-start gap-3">
                        <!--
                            The promoter's logo, the size of the count beside
                            it. Each disk is also a way in: the logo to the
                            promoter's details, the count to its events.
                        -->
                        <Link
                            v-if="panel.logoUrl"
                            :href="panel.manageUrl"
                            :aria-label="`Manage ${panel.name}`"
                            class="disk-link rounded-full"
                        >
                            <img
                                :src="panel.logoUrl"
                                :alt="`${panel.name} logo`"
                                class="logo-disk size-20 rounded-full bg-white object-cover md:size-24"
                            />
                        </Link>
                        <Link
                            :href="panel.eventsUrl"
                            :aria-label="`Manage events: ${panel.eventsListed} ${eventsListedLabel(panel.eventsListed).toLowerCase()}`"
                            class="disk-link flex flex-col items-center gap-2 rounded-full"
                        >
                            <span
                                class="events-disk flex size-20 items-center justify-center rounded-full bg-(--ink-muted) text-3xl font-semibold text-white tabular-nums md:size-24 md:text-4xl dark:bg-(--line)"
                            >
                                {{ panel.eventsListed }}
                            </span>
                            <span class="text-sm">
                                {{ eventsListedLabel(panel.eventsListed) }}
                            </span>
                        </Link>
                    </div>
                </section>

                <BookmarkedListings
                    v-if="bookmarkedListings.length"
                    :listings="bookmarkedListings"
                />
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/*
 * The count sits on a flat disk in the palette's own neutral grey (set on
 * the element): the muted ink in light mode, and in dark mode the line grey,
 * as the muted ink there is too pale for white type. A soft shadow lifts it
 * off the panel, and a fainter one lifts the number off the disk. A
 * promoter's logo beside it is lifted the same way.
 */
.events-disk,
.logo-disk {
    box-shadow:
        0 1px 2px rgb(0 0 0 / 0.12),
        0 6px 12px -4px rgb(0 0 0 / 0.3);
}

/*
 * A disk is a link too: it rises a little under the pointer, and shows a
 * ring when reached from the keyboard.
 */
.events-disk,
.logo-disk {
    transition: translate 150ms ease-out;
}

.disk-link:hover .events-disk,
.disk-link:hover .logo-disk {
    translate: 0 -2px;
}

.disk-link:focus-visible {
    outline: none;
}

.disk-link:focus-visible .events-disk,
.disk-link:focus-visible .logo-disk {
    outline: 2px solid var(--ink);
    outline-offset: 3px;
}

@media (prefers-reduced-motion: reduce) {
    .disk-link:hover .events-disk,
    .disk-link:hover .logo-disk {
        translate: none;
    }
}

.events-disk {
    text-shadow:
        0 1px 1px rgb(0 0 0 / 0.2),
        0 2px 6px rgb(0 0 0 / 0.25);
}
</style>
