<script setup lang="ts">
import { Leaf, Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const options = [
    { value: 'system', label: 'System', icon: Monitor },
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'goblin', label: 'Goblin', icon: Leaf },
] as const;
</script>

<template>
    <!--
        A row wherever there is room for one. A collapsed sidebar is only an
        icon wide, so there the row stands on end and the buttons shrink to
        the size of the icons above them.
    -->
    <div
        role="group"
        aria-label="Appearance"
        data-test="appearance-chooser"
        class="inline-flex gap-0.5 rounded-full border border-sidebar-border bg-background p-1 group-data-[collapsible=icon]:flex-col group-data-[collapsible=icon]:p-0"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :title="option.label"
            :aria-label="option.label"
            :aria-pressed="appearance === option.value"
            class="flex size-11 cursor-pointer items-center justify-center rounded-full transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring group-data-[collapsible=icon]:size-8"
            :class="
                appearance === option.value
                    ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                    : 'text-muted-foreground hover:bg-accent hover:text-foreground'
            "
            @click="updateAppearance(option.value)"
        >
            <component :is="option.icon" class="size-4" aria-hidden="true" />
        </button>
    </div>
</template>
