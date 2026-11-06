<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdvertController from '@/actions/App/Http/Controllers/Dashboard/AdvertController';
import { useAccountCrumb } from '@/composables/useAccountName';
import { type BreadcrumbItem } from '@/types';
import Heading from '../../../components/Heading.vue';
import AppLayout from '../../../layouts/AppLayout.vue';

const breadcrumbs: BreadcrumbItem[] = [
    useAccountCrumb(),
    { title: 'Adverts', href: AdvertController.index().url },
];

/**
 * The two halves an advert list will have. Laid out now so the shape of
 * the page is settled before anything is tied to an account.
 */
const sections = [
    {
        title: 'Running now',
        description: 'What is in the current issue and on the site.',
    },
    {
        title: 'Previous adverts',
        description: 'Everything you have run with us before.',
    },
];
</script>

<template>
    <Head title="Adverts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-6 p-4">
            <Heading
                title="Adverts"
                description="Your advertising with Good on Paper."
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

            <p class="text-sm text-muted-foreground">
                Adverts are booked and built by the Good on Paper office — email
                <a
                    href="mailto:help@goodonpaper.info"
                    class="underline underline-offset-4"
                    >help@goodonpaper.info</a
                >
                to take out an advert or change one.
            </p>
        </div>
    </AppLayout>
</template>
