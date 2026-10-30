<script setup lang="ts">
import ColourField from './ColourField.vue';

export type BrandColours = {
    brand_colour: string | null;
    accent_colour1: string | null;
    accent_colour2: string | null;
    brand_background_colour: string | null;
};

defineProps<{
    colours: BrandColours;
    errors: Partial<Record<string, string>>;
    hint?: string;
}>();

const fields = [
    { name: 'brand_colour', label: 'Brand colour' },
    { name: 'accent_colour1', label: 'Accent colour' },
    { name: 'accent_colour2', label: 'Second accent colour' },
    { name: 'brand_background_colour', label: 'Background colour' },
] as const;
</script>

<template>
    <fieldset class="grid gap-3">
        <legend class="text-sm font-medium">Brand colours</legend>
        <p v-if="hint" class="text-sm text-muted-foreground">{{ hint }}</p>

        <div class="grid gap-4 sm:grid-cols-2">
            <ColourField
                v-for="field in fields"
                :key="field.name"
                :name="field.name"
                :label="field.label"
                :model-value="colours[field.name]"
                :error="errors[field.name]"
            />
        </div>
    </fieldset>
</template>
