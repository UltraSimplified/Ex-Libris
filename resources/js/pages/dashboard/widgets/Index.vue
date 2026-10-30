<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import WidgetController from '@/actions/App/Http/Controllers/Dashboard/WidgetController';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import RowLink from '../../../components/dashboard/RowLink.vue';
import Heading from '../../../components/Heading.vue';
import { Button } from '../../../components/ui/button';
import AppLayout from '../../../layouts/AppLayout.vue';

type Widget = {
    id: number;
    name: string;
    owner: string | null;
    template: string;
    density: string;
    active: boolean;
};

type Named = { id: number; name: string } | null;

const props = defineProps<{
    widgets: Widget[];
    venue: Named;
    promoter: Named;
    status?: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Gig widgets', href: WidgetController.index().url },
];

/**
 * A new widget starts out for whichever venue or promoter the list was
 * narrowed to.
 */
const createUrl = WidgetController.create.url({
    query: props.venue
        ? { venue: props.venue.id }
        : props.promoter
          ? { promoter: props.promoter.id }
          : {},
});

const scopeName = props.venue?.name ?? props.promoter?.name;
</script>

<template>
    <Head title="Gig widgets" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-6 p-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    title="Gig widgets"
                    :subject="scopeName"
                    description="Your upcoming events on your own website, kept up to date from Good on Paper. Make a widget, then paste its snippet into your site."
                />
                <Button as-child>
                    <Link :href="createUrl">New widget</Link>
                </Button>
            </div>

            <p
                v-if="status"
                class="rounded-md border border-green-600/30 bg-green-600/10 px-4 py-2 text-sm text-green-700 dark:text-green-500"
            >
                {{ status }}
            </p>

            <div
                v-if="widgets.length"
                class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Widget</th>
                            <th class="px-4 py-3 font-medium">Showing</th>
                            <th class="px-4 py-3 font-medium">Style</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <RowLink
                            v-for="widget in widgets"
                            :key="widget.id"
                            :href="WidgetController.edit(widget.id).url"
                            :label="`Edit ${widget.name}`"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ widget.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ widget.owner ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ widget.template }},
                                {{ widget.density.toLowerCase() }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ widget.active ? 'On' : 'Switched off' }}
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
                    No widgets yet. Make one to show your events on your own
                    website.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
