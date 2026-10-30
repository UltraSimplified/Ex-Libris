<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { AppShellVariant } from '@/types';
import { SidebarProvider } from './ui/sidebar';

type Props = {
    variant?: AppShellVariant;
};

defineProps<Props>();

const isOpen = usePage().props.sidebarOpen;
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <slot />
    </div>
    <SidebarProvider v-else :default-open="isOpen">
        <slot />
    </SidebarProvider>
</template>
