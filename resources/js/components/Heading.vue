<script setup lang="ts">
type Props = {
    title: string;
    /**
     * The name of the thing being edited, which reads as part of the title
     * rather than as a note under it - so it is set at the same size and
     * weight, and told apart by colour alone.
     */
    subject?: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    subject: undefined,
    description: undefined,
    variant: 'default',
});
</script>

<template>
    <header :class="variant === 'small' ? '' : 'mb-8 space-y-0.5'">
        <h2
            :class="
                variant === 'small'
                    ? 'mb-0.5 text-base font-medium uppercase'
                    : 'text-xl font-semibold tracking-tight uppercase'
            "
        >
            {{ title }}
        </h2>
        <p
            v-if="subject"
            class="text-xl font-semibold tracking-tight text-muted-foreground uppercase"
        >
            {{ subject }}
        </p>
        <slot />
        <p v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
        </p>
    </header>
</template>
