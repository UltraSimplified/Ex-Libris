<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import ArticleController from '@/actions/App/Http/Controllers/Dashboard/ArticleController';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import Heading from '../../../components/Heading.vue';
import AppLayout from '../../../layouts/AppLayout.vue';

const props = defineProps<{
    /** Which kind of writing this is, if the sidebar said. */
    section?: string | null;
}>();

/**
 * The section being written for, or the lot. The sidebar offers the four
 * kinds of thing the magazine publishes rather than one Articles entry,
 * so the page says which of them it is showing.
 */
const heading = computed<string>(() => props.section ?? 'Articles');

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Articles', href: ArticleController.index().url },
    ...(props.section ? [{ title: props.section }] : []),
]);

/**
 * What a writer will find here. Laid out now so the shape of the page is
 * settled before anything actually hangs off it: features are written and
 * edited in the Office, and nothing yet brings a writer their own.
 */
const sections = [
    {
        title: 'Published',
        description: 'What you have written that has gone out in an issue.',
    },
    {
        title: 'In progress',
        description: 'Anything commissioned or being worked on.',
    },
];
</script>

<template>
    <Head :title="heading" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-6 p-4">
            <Heading
                :title="heading"
                description="What you have produced for Good on Paper."
            />

            <div
                v-for="section in sections"
                :key="section.title"
                class="space-y-3"
            >
                <Heading
                    variant="small"
                    :title="section.title"
                    :description="section.description"
                />

                <div
                    class="rounded-xl border border-dashed border-sidebar-border/70 p-8 text-center dark:border-sidebar-border"
                >
                    <p class="text-sm text-muted-foreground">
                        Nothing here yet.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
