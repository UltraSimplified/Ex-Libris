<script setup lang="ts">
import { ref, watch } from 'vue';
import InputError from '../InputError.vue';
import { Label } from '../ui/label';

const props = withDefaults(
    defineProps<{
        name: string;
        label: string;
        modelValue?: string | null;
        error?: string;
    }>(),
    {
        modelValue: null,
        error: undefined,
    },
);

/**
 * A native colour input cannot be empty - asked what it holds it answers
 * black - so the value that actually gets posted is kept here and the
 * input is only ever used to pick with. Unset posts the empty string,
 * which the request reads as null, and shows as a chequerboard rather
 * than as a colour nobody chose.
 */
const chosen = ref<string | null>(props.modelValue);

/*
 * Told about every change too, for anything that wants to show the colour
 * as it is picked - the widget preview - with v-model.
 */
const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>();

watch(chosen, (value) => {
    if (value !== props.modelValue) {
        emit('update:modelValue', value);
    }
});

watch(
    () => props.modelValue,
    (value) => {
        chosen.value = value;
    },
);

const onPick = (event: Event): void => {
    chosen.value = (event.target as HTMLInputElement).value;
};
</script>

<template>
    <div class="grid gap-2">
        <Label :for="name">{{ label }}</Label>

        <div class="flex items-center gap-2">
            <div
                class="relative h-10 flex-1 overflow-hidden rounded-md border border-input"
            >
                <!-- Chequerboard, showing through wherever nothing is set. -->
                <div
                    v-if="!chosen"
                    aria-hidden="true"
                    class="absolute inset-0 bg-[image:linear-gradient(45deg,var(--color-muted)_25%,transparent_25%),linear-gradient(-45deg,var(--color-muted)_25%,transparent_25%),linear-gradient(45deg,transparent_75%,var(--color-muted)_75%),linear-gradient(-45deg,transparent_75%,var(--color-muted)_75%)] bg-[length:12px_12px] bg-[position:0_0,0_6px,6px_-6px,-6px_0]"
                />
                <div
                    v-else
                    aria-hidden="true"
                    class="absolute inset-0"
                    :style="{ backgroundColor: chosen }"
                />

                <input
                    :id="name"
                    type="color"
                    :value="chosen ?? '#000000'"
                    class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                    @input="onPick"
                />
            </div>

            <button
                v-if="chosen"
                type="button"
                class="shrink-0 text-sm text-muted-foreground underline-offset-4 hover:underline"
                @click="chosen = null"
            >
                Clear
            </button>
        </div>

        <!-- What is actually posted, so an unset colour stays unset. -->
        <input type="hidden" :name="name" :value="chosen ?? ''" />

        <InputError :message="error" />
    </div>
</template>
