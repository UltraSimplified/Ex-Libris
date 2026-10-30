<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import PromoterController from '@/actions/App/Http/Controllers/Dashboard/PromoterController';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import BrandFields from '../../../components/dashboard/BrandFields.vue';
import FormErrors from '../../../components/dashboard/FormErrors.vue';
import ImageField from '../../../components/dashboard/ImageField.vue';
import Heading from '../../../components/Heading.vue';
import InputError from '../../../components/InputError.vue';
import { Button } from '../../../components/ui/button';
import { Input } from '../../../components/ui/input';
import { Label } from '../../../components/ui/label';
import AppLayout from '../../../layouts/AppLayout.vue';

type Promoter = {
    id: number;
    name: string;
    website: string | null;
    email: string | null;
    phone: string | null;
    about: string | null;
    logo: string | null;
    banner: string | null;
    brand_colour: string | null;
    accent_colour1: string | null;
    accent_colour2: string | null;
    brand_background_colour: string | null;
};

const props = defineProps<{ promoter: Promoter }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Promoter pages', href: PromoterController.index().url },
    {
        title: props.promoter.name,
        href: PromoterController.edit(props.promoter.id).url,
    },
];

const fieldClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] md:text-sm';
</script>

<template>
    <Head :title="`Edit ${promoter.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-3xl flex-1 space-y-6 p-4">
            <Heading title="Edit promoter" :subject="promoter.name" />

            <Form
                v-bind="PromoterController.update.form(promoter.id)"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <FormErrors :errors="errors" subject="promoter" />

                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="promoter.name"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                :default-value="promoter.email ?? ''"
                                placeholder="tickets@example.com"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="phone">Phone</Label>
                            <Input
                                id="phone"
                                name="phone"
                                :default-value="promoter.phone ?? ''"
                            />
                            <InputError :message="errors.phone" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="website">Website</Label>
                        <Input
                            id="website"
                            name="website"
                            type="url"
                            :default-value="promoter.website ?? ''"
                            placeholder="https://example.com"
                        />
                        <InputError :message="errors.website" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="about">About</Label>
                        <textarea
                            id="about"
                            name="about"
                            rows="6"
                            :class="fieldClass"
                            placeholder="A short introduction to who you are and what you put on."
                            :value="promoter.about ?? ''"
                        ></textarea>
                        <InputError :message="errors.about" />
                    </div>

                    <ImageField
                        name="logo"
                        label="Logo"
                        :existing="promoter.logo"
                        shape="square"
                        hint="JPEG, PNG or WebP, up to 8MB. Cropped square to 512×512, filled from the middle."
                        :error="errors.logo"
                    />

                    <ImageField
                        name="banner"
                        label="Banner"
                        shape="cover"
                        :existing="promoter.banner"
                        hint="JPEG, PNG or WebP, up to 8MB. Kept in its own shape, at up to 1280 pixels wide."
                        :error="errors.banner"
                    />

                    <BrandFields
                        :colours="promoter"
                        :errors="errors"
                        hint="Your gig widgets are painted in these. Leave any unset to use Good on Paper's own."
                    />
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="processing" type="submit">
                        {{ processing ? 'Saving…' : 'Save changes' }}
                    </Button>
                    <Link
                        :href="PromoterController.index()"
                        class="text-sm text-muted-foreground underline-offset-4 hover:underline"
                    >
                        Cancel
                    </Link>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
