<script setup lang="ts">
import type { BreadcrumbItem } from '@/types';
import AppContent from '../../components/AppContent.vue';
import AppearanceChooser from '../../components/AppearanceChooser.vue';
import AppShell from '../../components/AppShell.vue';
import AppSidebar from '../../components/AppSidebar.vue';
import AppSidebarHeader from '../../components/AppSidebarHeader.vue';
import SwitchedAccountBar from '../../components/SwitchedAccountBar.vue';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <SwitchedAccountBar />
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
            <!-- A phone has no sidebar on show, so the chooser ends the page. -->
            <div class="mt-auto flex justify-center p-4 md:hidden">
                <AppearanceChooser />
            </div>
        </AppContent>
    </AppShell>
</template>
