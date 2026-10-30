<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { edit } from '@/routes/notifications';
import { type BreadcrumbItem } from '@/types';
import Heading from '../../components/Heading.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import SettingsLayout from '../../layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Notification settings',
        href: edit().url,
    },
];

/**
 * What an account will get to choose. Laid out now so the shape is settled
 * before anything records a preference.
 */
const sections = [
    {
        title: 'Notifications',
        description: 'We can alert you to events that you have bookmarked',
    },
    {
        title: 'How often',
        description:
            'Whether that is every issue, monthly, or only when something big is on.',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Notification settings" />

        <h1 class="sr-only">Notification Settings</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    title="Notification settings"
                    description="What you hear from us, and how often"
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
                            Nothing to choose yet.
                        </p>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
