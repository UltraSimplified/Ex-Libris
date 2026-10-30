<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import BookmarkController from '@/actions/App/Http/Controllers/Dashboard/BookmarkController';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import BookmarkHeart from '../../../components/GOP/BookmarkHeart.vue';
import Heading from '../../../components/Heading.vue';
import { Button } from '../../../components/ui/button';
import AppLayout from '../../../layouts/AppLayout.vue';

type Listing = {
    id: number;
    title: string;
    slug: string;
    start_date: string | null;
    thumbnail: string | null;
    venue?: { name: string } | null;
    category?: { name: string } | null;
};

defineProps<{
    listings: Listing[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'My bookmarks', href: BookmarkController.index().url },
];

const page = usePage();

const thumbnailUrl = (listing: Listing): string | null =>
    listing.thumbnail
        ? `${String(page.props.cdnUrl).replace(/\/$/, '')}/${listing.thumbnail}`
        : null;

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
    <Head title="My bookmarks" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-6 p-4">
            <Heading
                title="My bookmarks"
                description="Events you have saved to come back to."
            />

            <div
                v-if="listings.length"
                class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">
                                <span class="sr-only">Image</span>
                            </th>
                            <th class="px-4 py-3 font-medium">Title</th>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Venue</th>
                            <th class="px-4 py-3 font-medium">Category</th>
                            <th class="px-4 py-3 text-right font-medium">
                                Saved
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="listing in listings"
                            :key="listing.id"
                            class="border-t border-sidebar-border/70 dark:border-sidebar-border"
                        >
                            <td class="py-3 pl-4">
                                <img
                                    v-if="thumbnailUrl(listing)"
                                    :src="thumbnailUrl(listing) ?? ''"
                                    alt=""
                                    class="h-12 w-14 rounded object-cover"
                                />
                                <div
                                    v-else
                                    class="h-12 w-14 rounded border border-dashed border-sidebar-border/70 dark:border-sidebar-border"
                                ></div>
                            </td>
                            <td class="px-4 py-3 font-medium">
                                <Link
                                    :href="`/listings/${listing.id}/${listing.slug}`"
                                    class="underline-offset-4 hover:underline"
                                >
                                    {{ listing.title }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ formatDate(listing.start_date) }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ listing.venue?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ listing.category?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end">
                                    <BookmarkHeart :listing-id="listing.id" />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-else
                class="rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">
                    You have not saved any listings yet.
                </p>
                <Button as-child class="mt-4">
                    <Link href="/listings">Browse what's on</Link>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
