<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import VenueController from '@/actions/App/Http/Controllers/Dashboard/VenueController';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import RowLink from '../../../components/dashboard/RowLink.vue';
import Heading from '../../../components/Heading.vue';
import AppLayout from '../../../layouts/AppLayout.vue';

type Venue = {
    id: number;
    name: string;
    town: string | null;
    postcode: string | null;
    listings_count: number;
};

defineProps<{
    venues: Venue[];
    status?: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Venues', href: VenueController.index().url },
];

const events = (count: number): string =>
    count === 1 ? '1 event on' : `${count} events on`;
</script>

<template>
    <Head title="Venues" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-6 p-4">
            <Heading
                title="Venues"
                description="The venues you look after. You can edit anything listed at them, whoever put the event on."
            />

            <p
                v-if="status"
                class="rounded-md border border-green-600/30 bg-green-600/10 px-4 py-2 text-sm text-green-700 dark:text-green-500"
            >
                {{ status }}
            </p>

            <div
                v-if="venues.length"
                class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Venue</th>
                            <th class="px-4 py-3 font-medium">Town</th>
                            <th class="px-4 py-3 font-medium">Coming up</th>
                        </tr>
                    </thead>
                    <tbody>
                        <RowLink
                            v-for="venue in venues"
                            :key="venue.id"
                            :href="VenueController.edit(venue.id).url"
                            :label="`Edit ${venue.name}`"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ venue.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ venue.town ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ events(venue.listings_count) }}
                            </td>
                        </RowLink>
                    </tbody>
                </table>
            </div>

            <div
                v-else
                class="rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">
                    No venues are linked to your account yet.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
