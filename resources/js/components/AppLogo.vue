<script setup lang="ts">
import { computed } from 'vue';
import { useAccountName } from '@/composables/useAccountName';
import AppLogoIcon from './AppLogoIcon.vue';

const accountName = useAccountName();

/** BACKSTAGE. for those who work with the magazine, MY ACCOUNT. for guests. */
const label = computed(() =>
    accountName.value === 'Backstage' ? 'BACKSTAGE.' : 'MY ACCOUNT.',
);
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            label
        }}</span>
    </div>
</template>
