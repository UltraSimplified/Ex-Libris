<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PromoterController from '@/actions/App/Http/Controllers/Dashboard/PromoterController';
import { useAccountCrumb } from '@/composables/useAccountName';
import { type BreadcrumbItem } from '@/types';
import RowLink from '../../../components/dashboard/RowLink.vue';
import Heading from '../../../components/Heading.vue';
import AppLayout from '../../../layouts/AppLayout.vue';

type Promoter = {
    id: number;
    name: string;
    email: string | null;
    website: string | null;
    listings_count: number;
};

defineProps<{
    promoters: Promoter[];
    status?: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    useAccountCrumb(),
    { title: 'Promoter pages', href: PromoterController.index().url },
];

const events = (count: number): string =>
    count === 1 ? '1 event on' : `${count} events on`;
</script>

<template>
    <Head title="Promoter pages" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-6 p-4">
            <Heading
                title="Promoter pages"
                description="The promoters you look after. Keep their details up to date and they show correctly wherever their events appear."
            />

            <p
                v-if="status"
                class="rounded-md border border-green-600/30 bg-green-600/10 px-4 py-2 text-sm text-green-700 dark:text-green-500"
            >
                {{ status }}
            </p>

            <div
                v-if="promoters.length"
                class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Promoter</th>
                            <th class="px-4 py-3 font-medium">Contact</th>
                            <th class="px-4 py-3 font-medium">Coming up</th>
                        </tr>
                    </thead>
                    <tbody>
                        <RowLink
                            v-for="promoter in promoters"
                            :key="promoter.id"
                            :href="PromoterController.edit(promoter.id).url"
                            :label="`Edit ${promoter.name}`"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ promoter.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ promoter.email ?? promoter.website ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ events(promoter.listings_count) }}
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
                    No promoters are linked to your account yet.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
