<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import InputError from '../InputError.vue';
import { Label } from '../ui/label';

const props = withDefaults(
    defineProps<{
        /** Posted as this field, with remove_<name> beside it. */
        name: string;
        label: string;
        /** Whatever is on the record now, as a path on the Spaces disk. */
        existing?: string | null;
        /**
         * The shape it will be cropped to, which the preview matches. A
         * cover keeps the shape it was taken in and is shown as large as
         * the venue page sets it, so the owner sees what visitors will.
         */
        shape?: 'banner' | 'square' | 'cover';
        hint?: string;
        error?: string;
    }>(),
    {
        existing: null,
        shape: 'banner',
        hint: undefined,
        error: undefined,
    },
);

const page = usePage();

/** Stored as a filename, served from the CDN. */
const existingUrl = computed<string | null>(() =>
    props.existing
        ? `${String(page.props.cdnUrl).replace(/\/$/, '')}/${props.existing.replace(/^\//, '')}`
        : null,
);

const preview = ref<string | null>(null);
const remove = ref(false);

const releasePreview = (): void => {
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
        preview.value = null;
    }
};

const onChange = (event: Event): void => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    releasePreview();

    if (file) {
        preview.value = URL.createObjectURL(file);
        remove.value = false;
    }
};

onBeforeUnmount(releasePreview);

const shown = computed<string | null>(
    () => preview.value ?? (remove.value ? null : existingUrl.value),
);

/** The preview stands at the ratio the upload will be cropped to. */
const frameClass = computed<string>(
    () =>
        ({
            square: 'size-24',
            banner: 'h-[99px] w-40',
            cover: 'h-auto w-full max-w-[666px] rounded-md',
        })[props.shape],
);

const pickerButtonClass =
    'inline-flex cursor-pointer items-center gap-2 rounded-md border border-input bg-transparent px-3 py-2 text-sm font-medium shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground focus-within:ring-[3px] focus-within:ring-ring/50';
</script>

<template>
    <div class="grid gap-2">
        <Label :for="name">{{ label }}</Label>

        <div
            :class="
                shape === 'cover'
                    ? 'flex flex-col gap-4'
                    : 'flex items-start gap-4'
            "
        >
            <img
                v-if="shown"
                :src="shown"
                alt=""
                :class="[
                    frameClass,
                    'shrink-0 border border-sidebar-border/70 object-cover dark:border-sidebar-border',
                ]"
            />
            <div
                v-else
                :class="[
                    frameClass,
                    shape === 'cover' ? 'aspect-[1280/794]' : '',
                    'flex shrink-0 items-center justify-center border border-dashed border-sidebar-border/70 text-xs text-muted-foreground dark:border-sidebar-border',
                ]"
            >
                No image
            </div>

            <div class="grid flex-1 gap-2">
                <div>
                    <label :for="name" :class="pickerButtonClass">
                        Choose image
                    </label>
                    <input
                        :id="name"
                        :name="name"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        @change="onChange"
                    />
                </div>

                <p v-if="hint" class="text-xs text-muted-foreground">
                    {{ hint }}
                </p>

                <label
                    v-if="existingUrl"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        v-model="remove"
                        type="checkbox"
                        :name="`remove_${name}`"
                        value="1"
                        class="size-4 rounded border-input"
                    />
                    Remove the current image
                </label>
            </div>
        </div>

        <InputError :message="error" />
    </div>
</template>
