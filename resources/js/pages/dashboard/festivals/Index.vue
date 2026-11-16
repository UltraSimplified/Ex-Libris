<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import FestivalController from '@/actions/App/Http/Controllers/Dashboard/FestivalController';
import RowLink from '@/components/dashboard/RowLink.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useAccountCrumb } from '@/composables/useAccountName';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Managed } from '@/types';

type Festival = {
    id: number;
    name: string;
    promoter: Managed;
    listings_count: number;
};

const props = defineProps<{
    festivals: Festival[];
    /** The promoter the list is narrowed to, when it is. */
    promoter: Managed | null;
    status?: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    useAccountCrumb(),
    { title: 'Festivals', href: FestivalController.index().url },
];

const createHref = FestivalController.create.url(
    props.promoter ? { query: { promoter: props.promoter.id } } : undefined,
);

const events = (count: number): string =>
    count === 1 ? '1 event on' : `${count} events on`;
</script>

<template>
    <Head title="Festivals" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-6 p-4">
            <Heading
                title="Festivals"
                :subject="promoter?.name"
                description="Sets of your events under a name and look of their own. Put a listing in one from its Festival choice."
            >
                <Button as-child>
                    <Link :href="createHref">New festival</Link>
                </Button>
            </Heading>

            <p
                v-if="status"
                class="rounded-md border border-green-600/30 bg-green-600/10 px-4 py-2 text-sm text-green-700 dark:text-green-500"
            >
                {{ status }}
            </p>

            <div
                v-if="festivals.length"
                class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Festival</th>
                            <th class="px-4 py-3 font-medium">Promoter</th>
                            <th class="px-4 py-3 font-medium">Coming up</th>
                        </tr>
                    </thead>
                    <tbody>
                        <RowLink
                            v-for="festival in festivals"
                            :key="festival.id"
                            :href="FestivalController.edit(festival.id).url"
                            :label="`Edit ${festival.name}`"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ festival.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ festival.promoter.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ events(festival.listings_count) }}
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
                    No festivals yet. Make one, then put your listings in it.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
