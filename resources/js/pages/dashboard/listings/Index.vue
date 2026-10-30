<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ListingController from '@/actions/App/Http/Controllers/Dashboard/ListingController';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import ListingPreview from '../../../components/dashboard/ListingPreview.vue';
import RowLink from '../../../components/dashboard/RowLink.vue';
import Heading from '../../../components/Heading.vue';
import { Button } from '../../../components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '../../../components/ui/dialog';
import AppLayout from '../../../layouts/AppLayout.vue';

type Listing = {
    id: number;
    title: string;
    start_date: string | null;
    thumbnail: string | null;
    sold_out: boolean;
    /** How many people have saved it. */
    bookmarked_by_count: number;
    venue?: { name: string } | null;
    promoter?: { name: string } | null;
    category?: { name: string } | null;
};

/** What the list has been narrowed to, if anything. */
type Narrowed = { id: number; name: string } | null;

const props = defineProps<{
    listings: Listing[];
    /** Whether what is shown includes events that have already been. */
    showingAll: boolean;
    /** How many are behind the "everything" link. */
    pastCount: number;
    /** The venue this is narrowed to, reached from its own section. */
    venue?: Narrowed;
    /** The promoter this is narrowed to, likewise. */
    promoter?: Narrowed;
    status?: string;
    /**
     * Whether this account may add and edit listings: only those the
     * office has linked to a promoter or venue. Anybody else can still
     * read and delete what they sent in before.
     */
    canPublish: boolean;
}>();

/**
 * Whose listings these are. The sidebar gives every venue and promoter a
 * section of its own, so this page has to say which one it is showing
 * rather than leaving the heading to claim all of them.
 */
const heading = computed<string>(
    () => props.venue?.name ?? props.promoter?.name ?? 'Event Listings',
);

const description = computed<string>(() => {
    if (props.venue) {
        return `Everything you have added at ${props.venue.name}.`;
    }

    if (props.promoter) {
        return `Everything you have added under ${props.promoter.name}.`;
    }

    return 'Everything you have added to Good on Paper.';
});

/**
 * The address for this same list at the other scope. Whichever venue or
 * promoter is being looked at is carried across, so changing how far back
 * the list reaches does not quietly widen it to everything.
 */
const scoped = (all: boolean): string =>
    ListingController.index.url({
        query: {
            ...(props.venue ? { venue: props.venue.id } : {}),
            ...(props.promoter ? { promoter: props.promoter.id } : {}),
            ...(all ? { all: 1 } : {}),
        },
    });

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Event Listings', href: ListingController.index().url },
    ...(props.venue || props.promoter
        ? [{ title: heading.value, href: scoped(props.showingAll) }]
        : []),
]);

const page = usePage();

const thumbnailUrl = (listing: Listing): string | null =>
    listing.thumbnail
        ? `${String(page.props.cdnUrl).replace(/\/$/, '')}/${listing.thumbnail}`
        : null;

/**
 * The listing somebody has asked to delete, waiting on them to confirm it
 * in the dialog. Nothing is deleted until they do.
 */
const deleting = ref<Listing | null>(null);

const closeDelete = (): void => {
    deleting.value = null;
};

/**
 * The row somebody has asked for more of, and where they asked. Held as a
 * point on the page rather than inside the row so the menu is not clipped
 * by the table it belongs to.
 */
const menu = ref<{ listing: Listing; x: number; y: number } | null>(null);

const showMenu = (listing: Listing, at: { x: number; y: number }): void => {
    menu.value = { listing, ...at };
};

const closeMenu = (): void => {
    menu.value = null;
};

/** The listing being read as the public will see it, if any. */
const previewing = ref<Listing | null>(null);

const preview = (): void => {
    previewing.value = menu.value?.listing ?? null;
    closeMenu();
};

const duplicate = (): void => {
    const listing = menu.value?.listing;

    closeMenu();

    if (listing) {
        router.post(ListingController.duplicate(listing.id).url);
    }
};

/** Anywhere else, and any other page, closes it. */
const closeOnEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        closeMenu();
    }
};

onMounted(() => {
    window.addEventListener('keydown', closeOnEscape);
    window.addEventListener('resize', closeMenu);
    window.addEventListener('scroll', closeMenu, true);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', closeOnEscape);
    window.removeEventListener('resize', closeMenu);
    window.removeEventListener('scroll', closeMenu, true);
});

const formatDate = (value: string | null): string =>
    value
        ? new Date(value).toLocaleDateString('en-GB', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : 'No date';
</script>

<template>
    <Head :title="heading" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-6 p-4">
            <div class="flex items-start justify-between gap-4">
                <Heading :title="heading" :description="description" />
                <Button v-if="canPublish" as-child>
                    <Link :href="ListingController.create()">
                        Add a listing
                    </Link>
                </Button>
            </div>

            <p
                v-if="status"
                class="rounded-md border border-green-600/30 bg-green-600/10 px-4 py-2 text-sm text-green-700 dark:text-green-500"
            >
                {{ status }}
            </p>

            <p
                v-if="!canPublish"
                class="rounded-md border border-sidebar-border/70 px-4 py-2 text-sm text-muted-foreground dark:border-sidebar-border"
            >
                Listings are added by the Good on Paper office and the venues
                and promoters it works with. Get in touch with the office to
                list an event.
            </p>

            <!--
                What is still to come, unless somebody asks for the lot: an
                account that has been listing for a while has far more
                behind it than ahead of it.
            -->
            <p v-if="pastCount > 0" class="text-sm text-muted-foreground">
                <template v-if="showingAll">
                    Showing all /
                    <Link
                        :href="scoped(false)"
                        class="underline underline-offset-4"
                    >
                        Show only current listings
                    </Link>
                </template>
                <template v-else>
                    Showing upcoming /
                    <Link
                        :href="scoped(true)"
                        class="underline underline-offset-4"
                    >
                        Show all
                    </Link>
                </template>
            </p>

            <div
                v-if="listings.length"
                class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="py-3 pl-3 font-medium md:pl-4">
                                <span class="sr-only">Image</span>
                            </th>
                            <th class="px-2 py-3 font-medium md:px-4">Title</th>
                            <th class="px-2 py-3 font-medium md:px-4">
                                Favourites
                            </th>
                            <th
                                class="py-3 pr-3 pl-2 text-right font-medium md:px-4"
                            >
                                <span class="sr-only">Delete</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <!--
                            A row opens the listing to edit, for an account
                            that may; anybody else's rows are only read.
                        -->
                        <component
                            :is="canPublish ? RowLink : 'tr'"
                            v-for="listing in listings"
                            :key="listing.id"
                            v-bind="
                                canPublish
                                    ? {
                                          href: ListingController.edit(
                                              listing.id,
                                          ).url,
                                          label: `Edit ${listing.title}`,
                                      }
                                    : {
                                          class: 'border-t border-sidebar-border/70 dark:border-sidebar-border',
                                      }
                            "
                            @context="showMenu(listing, $event)"
                        >
                            <!--
                                The thumbnail at its own shape, 56px wide,
                                rather than cropped into a box: the cell
                                holds its width however tight the row.
                                The empty one is the shape thumbnails are
                                saved at, 548 by 462.
                            -->
                            <td class="w-14 min-w-14 py-3 pl-3 md:pl-4">
                                <img
                                    v-if="thumbnailUrl(listing)"
                                    :src="thumbnailUrl(listing) ?? ''"
                                    alt=""
                                    class="h-auto w-14 max-w-none rounded"
                                />
                                <div
                                    v-else
                                    class="aspect-[548/462] w-14 rounded border border-dashed border-sidebar-border/70 dark:border-sidebar-border"
                                ></div>
                            </td>
                            <td class="px-2 py-3 font-medium md:px-4">
                                {{ listing.title }}
                                <span
                                    v-if="listing.sold_out"
                                    class="ml-2 inline-block rounded-full border border-red-600/40 px-2 py-0.5 text-xs font-normal text-red-700 dark:text-red-500"
                                >
                                    Sold out
                                </span>
                                <!--
                                    The date and venue under the title, at
                                    every width: the row is laid out for a
                                    phone first.
                                -->
                                <span
                                    class="mt-1 block text-xs font-normal text-muted-foreground"
                                >
                                    {{ formatDate(listing.start_date) }}
                                    <template v-if="listing.venue">
                                        &middot; {{ listing.venue.name }}
                                    </template>
                                </span>
                            </td>
                            <!--
                                The count on its own, given some weight:
                                the column is already headed, and this is
                                a number somebody comes back to watch.
                            -->
                            <td class="px-2 py-3 text-base font-bold md:px-4">
                                {{ listing.bookmarked_by_count }}
                            </td>
                            <td class="py-3 pr-3 pl-2 md:px-4">
                                <div class="flex flex-col items-end gap-2">
                                    <button
                                        type="button"
                                        class="cursor-pointer text-sm underline-offset-4 hover:underline"
                                        @click="previewing = listing"
                                    >
                                        Preview
                                    </button>
                                    <button
                                        type="button"
                                        class="cursor-pointer text-sm text-red-600 underline-offset-4 hover:underline dark:text-red-500"
                                        data-test="delete-listing"
                                        @click="deleting = listing"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </component>
                    </tbody>
                </table>
            </div>

            <div
                v-else
                class="rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center dark:border-sidebar-border"
            >
                <p
                    v-if="!showingAll && pastCount > 0"
                    class="text-sm text-muted-foreground"
                >
                    Nothing coming up.
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    You have not added any listings yet.
                </p>
                <Button v-if="canPublish" as-child class="mt-4">
                    <Link :href="ListingController.create()">
                        Add your first listing
                    </Link>
                </Button>
            </div>
        </div>

        <!--
            Right-click, or hold a finger on a row. Rendered against the
            page rather than inside the table, which would clip it, and
            behind a sheet that catches the click that dismisses it.
        -->
        <template v-if="menu">
            <div
                class="fixed inset-0 z-40"
                @click="closeMenu"
                @contextmenu.prevent="closeMenu"
            ></div>
            <div
                role="menu"
                class="fixed z-50 min-w-52 overflow-hidden rounded-md border border-sidebar-border/70 bg-background py-1 shadow-lg dark:border-sidebar-border"
                :style="{ left: `${menu.x}px`, top: `${menu.y}px` }"
            >
                <p class="truncate px-3 py-1.5 text-xs text-muted-foreground">
                    {{ menu.listing.title }}
                </p>
                <button
                    type="button"
                    role="menuitem"
                    class="w-full px-3 py-2 text-left text-sm hover:bg-muted/60"
                    @click="preview"
                >
                    Preview listing
                </button>
                <button
                    type="button"
                    role="menuitem"
                    class="w-full px-3 py-2 text-left text-sm hover:bg-muted/60"
                    @click="duplicate"
                >
                    Duplicate listing
                </button>
            </div>
        </template>

        <ListingPreview
            v-if="previewing"
            :id="previewing.id"
            :title="previewing.title"
            @close="previewing = null"
        />

        <!-- Asks before a listing is deleted, naming the one at stake. -->
        <Dialog
            :open="deleting !== null"
            @update:open="(open) => open || closeDelete()"
        >
            <DialogContent v-if="deleting" class="sm:max-w-md">
                <Form
                    v-bind="ListingController.destroy.form(deleting.id)"
                    :options="{ preserveScroll: true }"
                    class="space-y-6"
                    @success="closeDelete"
                    v-slot="{ processing }"
                >
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Delete this listing?</DialogTitle>
                        <DialogDescription>
                            &ldquo;{{ deleting.title }}&rdquo; will be taken off
                            Good on Paper, along with its images. This cannot be
                            undone.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                            data-test="confirm-delete-listing"
                        >
                            {{ processing ? 'Deleting…' : 'Delete listing' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
