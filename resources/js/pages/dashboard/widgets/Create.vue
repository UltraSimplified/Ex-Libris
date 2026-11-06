<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import WidgetController from '@/actions/App/Http/Controllers/Dashboard/WidgetController';
import { useAccountCrumb } from '@/composables/useAccountName';
import { type BreadcrumbItem } from '@/types';
import FormErrors from '../../../components/dashboard/FormErrors.vue';
import Heading from '../../../components/Heading.vue';
import InputError from '../../../components/InputError.vue';
import { Button } from '../../../components/ui/button';
import { Input } from '../../../components/ui/input';
import { Label } from '../../../components/ui/label';
import AppLayout from '../../../layouts/AppLayout.vue';

type Choice = { value: string; label: string; description: string | null };

defineProps<{
    owners: { value: string; label: string }[];
    owner: string | null;
    templates: Choice[];
    densities: Choice[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    useAccountCrumb(),
    { title: 'Gig widgets', href: WidgetController.index().url },
    { title: 'New widget', href: WidgetController.create().url },
];

const fieldClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] md:text-sm';
</script>

<template>
    <Head title="New widget" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-3xl flex-1 space-y-6 p-4">
            <Heading
                title="New widget"
                description="Choose whose events it shows and how it looks. You can fine-tune it, with a preview, on the next page."
            />

            <Form
                v-bind="WidgetController.store.form()"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <FormErrors :errors="errors" subject="widget" />

                <div class="grid gap-2">
                    <Label for="owner">Events from</Label>
                    <select
                        id="owner"
                        name="owner"
                        :class="fieldClass"
                        required
                    >
                        <option
                            v-for="choice in owners"
                            :key="choice.value"
                            :value="choice.value"
                            :selected="choice.value === owner"
                        >
                            {{ choice.label }}
                        </option>
                    </select>
                    <InputError :message="errors.owner" />
                </div>

                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        name="name"
                        default-value="What's on"
                        required
                    />
                    <p class="text-sm text-muted-foreground">
                        Just for you, to tell your widgets apart - say "Home
                        page" or "Footer".
                    </p>
                    <InputError :message="errors.name" />
                </div>

                <input
                    type="hidden"
                    name="template"
                    :value="templates[0].value"
                />

                <fieldset class="grid gap-3">
                    <legend class="text-sm font-medium">Style</legend>
                    <label
                        v-for="(density, index) in densities"
                        :key="density.value"
                        class="flex cursor-pointer items-start gap-3 rounded-md border border-input p-3 has-[:checked]:border-foreground"
                    >
                        <input
                            type="radio"
                            name="density"
                            :value="density.value"
                            :checked="index === 2"
                            class="mt-1"
                        />
                        <span>
                            <span class="block text-sm font-medium">
                                {{ density.label }}
                            </span>
                            <span class="block text-sm text-muted-foreground">
                                {{ density.description }}
                            </span>
                        </span>
                    </label>
                    <InputError :message="errors.density" />
                </fieldset>

                <div class="flex items-center gap-4">
                    <Button :disabled="processing" type="submit">
                        {{ processing ? 'Making…' : 'Make widget' }}
                    </Button>
                    <Link
                        :href="WidgetController.index()"
                        class="text-sm text-muted-foreground underline-offset-4 hover:underline"
                    >
                        Cancel
                    </Link>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
