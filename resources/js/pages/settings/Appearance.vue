<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { edit } from '@/routes/appearance';
import { type BreadcrumbItem } from '@/types';
import AppearanceTabs from '../../components/AppearanceTabs.vue';
import Heading from '../../components/Heading.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import SettingsLayout from '../../layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: edit().url,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Appearance settings" />

        <h1 class="sr-only">Appearance Settings</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    title="Appearance settings"
                    description="Update your account's appearance settings"
                />
                <AppearanceTabs />
                <p class="text-sm text-muted-foreground">
                    Goblin is dark mode in green. It shows in the dashboard and
                    settings; on the rest of the site it is shown as Dark.
                </p>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
