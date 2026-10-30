<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';
import { syncTheme } from '@/composables/useAppearance';
import type { BreadcrumbItem } from '@/types';
import AppLayout from './app/AppSidebarLayout.vue';


type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

/**
 * Marks the page as part of the editing area, which is the only place
 * Goblin mode shows as green. Each page wraps itself in this layout, so the
 * mark goes up and comes down as the reader moves in and out.
 */
onMounted(() => {
    document.documentElement.dataset.editing = '';
    syncTheme();
});

onBeforeUnmount(() => {
    delete document.documentElement.dataset.editing;
    syncTheme();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
</template>
